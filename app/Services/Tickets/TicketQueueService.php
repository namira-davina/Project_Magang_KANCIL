<?php

namespace App\Services\Tickets;

use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Bot\BotScript;
use App\Services\Bot\BusinessHours;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Antrean tiket layanan petugas (FIFO): buka tiket, posisi antrean, ambil, selesaikan, batal, kedaluwarsa.
 *
 * Status: Menunggu -> Ditangani -> Selesai; Menunggu -> Batal (pemohon) / Kedaluwarsa (tidak terlayani).
 * Satu percakapan paling banyak punya satu tiket terbuka.
 */
class TicketQueueService
{
    public function __construct(
        private readonly BusinessHours $hours,
        private readonly WhatsappMessenger $messenger,
    ) {}

    /**
     * Buka tiket untuk percakapan, atau kembalikan tiket terbuka yang sudah ada.
     *
     * @return array{0: ServiceTicket, 1: bool} [tiket, baru dibuat]
     */
    public function open(Conversation $conversation, ?EscalationLog $log = null, ?string $question = null): array
    {
        // Kunci baris percakapan: dua worker yang memproses pesan pemohon yang sama tidak membuat dua tiket.
        return DB::transaction(function () use ($conversation, $log, $question) {
            Conversation::whereKey($conversation->id)->lockForUpdate()->first();

            if ($existing = $conversation->serviceTickets()->open()->latest('id')->first()) {
                return [$existing, false];
            }

            return [$this->create($conversation, $log, $question), true];
        });
    }

    private function create(Conversation $conversation, ?EscalationLog $log, ?string $question): ServiceTicket
    {
        return $this->withQueueNumber(fn (string $date, int $no) => ServiceTicket::create([
            'ticket_number' => 'LYN-'.str_replace('-', '', $date).'-'.str_pad((string) $no, 3, '0', STR_PAD_LEFT),
            'queue_date' => $date,
            'queue_no' => $no,
            'conversation_id' => $conversation->id,
            'escalation_log_id' => $log?->id,
            'escalation_code' => $log?->escalation_code,
            'priority' => $log?->priority,
            'menu_path' => $log?->menu_path,
            'question_text' => $question !== null && trim($question) !== '' ? mb_substr(trim($question), 0, 2000) : null,
            'status' => ServiceTicket::WAITING,
            'queued_at' => now(),
            'last_notice_at' => now(),
        ]));
    }

    /** Posisi di antrean (1 = berikutnya dilayani); null bila tiket tidak sedang menunggu. */
    public function position(ServiceTicket $ticket): ?int
    {
        if ($ticket->status !== ServiceTicket::WAITING) {
            return null;
        }

        return ServiceTicket::where('status', ServiceTicket::WAITING)
            ->where(fn ($q) => $q->where('queued_at', '<', $ticket->queued_at)
                ->orWhere(fn ($q) => $q->where('queued_at', $ticket->queued_at)->where('id', '<', $ticket->id)))
            ->count() + 1;
    }

    /** @return array<int, int> id tiket => posisi, untuk semua tiket yang menunggu (satu query). */
    public function positions(): array
    {
        return ServiceTicket::where('status', ServiceTicket::WAITING)->fifo()->pluck('id')
            ->values()->mapWithKeys(fn ($id, $i) => [$id => $i + 1])->all();
    }

    /**
     * Petugas mengambil/menangani percakapan (balas, ambil alih, atau ambil tiket).
     * Tiket menunggu jadi Ditangani; $responded = petugas benar-benar membalas (waktu respon pertama).
     */
    public function assign(Conversation $conversation, User $user, bool $responded = false): ?ServiceTicket
    {
        $this->guardOwnership($conversation, $user);

        $ticket = $conversation->serviceTickets()->open()->latest('id')->first();

        if ($ticket) {
            $this->guardTicket($ticket, $user);

            if ($ticket->status === ServiceTicket::WAITING) {
                // Update bersyarat: bila dua petugas menekan bersamaan, hanya satu yang menang.
                ServiceTicket::whereKey($ticket->id)->where('status', ServiceTicket::WAITING)->update([
                    'status' => ServiceTicket::HANDLING,
                    'assigned_to' => $user->id,
                    'taken_at' => now(),
                ]);

                $this->guardTicket($ticket->refresh(), $user);
            }
        }

        $conversation->update([
            'conversation_mode' => Conversation::MODE_HUMAN,
            'assigned_to' => $user->id,
            'bot_paused_until' => now()->addMinutes((int) config('kancil.handoff_minutes', 120)),
        ]);

        $conversation->escalationLogs()->chatHandoff()->where('status', 'Menunggu')->update([
            'status' => 'Diambil',
            'handled_by' => $user->id,
            'taken_at' => now(),
        ]);

        if (! $ticket) {
            return null;
        }

        $ticket->assigned_to = $user->id;
        $ticket->taken_at ??= now();

        if ($responded) {
            $ticket->first_response_at ??= now();
        }

        $ticket->save();

        return $ticket;
    }

    /** Tiket yang sedang ditangani petugas lain tidak boleh diambil petugas; Admin boleh mengambil alih. */
    private function guardTicket(ServiceTicket $ticket, User $user): void
    {
        if ($ticket->status === ServiceTicket::HANDLING && $ticket->assigned_to
            && $ticket->assigned_to !== $user->id && ! $user->hasRole('admin')) {
            throw TicketException::takenByOther($ticket->officer?->name);
        }
    }

    /** Ambil tiket tertentu dari halaman antrean. */
    public function claim(ServiceTicket $ticket, User $user): ServiceTicket
    {
        if (! $ticket->isOpen()) {
            throw TicketException::closed();
        }

        $this->guardTicket($ticket, $user);

        return $this->assign($ticket->conversation, $user);
    }

    /** Ambil tiket menunggu paling awal. Null bila antrean kosong. */
    public function claimNext(User $user): ?ServiceTicket
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $next = ServiceTicket::where('status', ServiceTicket::WAITING)->fifo()->first();

            if (! $next) {
                return null;
            }

            $won = ServiceTicket::whereKey($next->id)->where('status', ServiceTicket::WAITING)->update([
                'status' => ServiceTicket::HANDLING,
                'assigned_to' => $user->id,
                'taken_at' => now(),
            ]);

            if ($won) {
                try {
                    $this->assign($next->conversation, $user);
                } catch (TicketException $e) {
                    // Percakapan dipegang petugas lain: kembalikan tiket ke antrean.
                    ServiceTicket::whereKey($next->id)->update(['status' => ServiceTicket::WAITING, 'assigned_to' => null, 'taken_at' => null]);

                    throw $e;
                }

                return $next->fresh();
            }
        }

        return null;
    }

    /** Pesan petugas dari HP/aplikasi (Opsi B): tiket dianggap direspons, petugas tidak diketahui. */
    public function markRespondedExternally(Conversation $conversation): void
    {
        $conversation->serviceTickets()->open()->get()->each(function (ServiceTicket $ticket) {
            $ticket->forceFill([
                'status' => ServiceTicket::HANDLING,
                'taken_at' => $ticket->taken_at ?? now(),
                'first_response_at' => $ticket->first_response_at ?? now(),
            ])->save();
        });
    }

    /** Petugas menyelesaikan tiket: pemohon diberi tahu, bot aktif lagi. */
    public function resolve(ServiceTicket $ticket, User $user, bool $notify = true, ?string $reason = null): ServiceTicket
    {
        $this->authorize($ticket, $user);
        $conversation = $ticket->conversation;

        $ticket->update([
            'status' => ServiceTicket::RESOLVED,
            'assigned_to' => $ticket->assigned_to ?? $user->id,
            'taken_at' => $ticket->taken_at ?? now(),
            'resolved_at' => now(),
            'close_reason' => $reason ?? 'Diselesaikan petugas.',
        ]);

        $conversation->returnToBot(closeAll: true);

        if ($notify) {
            $this->messenger->queue($conversation, BotScript::ticketResolved($ticket->ticket_number), 'system', $user->id,
                meta: ['codes' => ['LYN-SELESAI'], 'ticket_id' => $ticket->id]);
        }

        return $ticket->fresh();
    }

    /** Pemohon mengetik "batal" saat menunggu. */
    public function cancel(ServiceTicket $ticket): void
    {
        if ($ticket->status !== ServiceTicket::WAITING) {
            throw TicketException::closed();
        }

        $ticket->update([
            'status' => ServiceTicket::CANCELLED,
            'resolved_at' => now(),
            'close_reason' => 'Dibatalkan pemohon.',
        ]);

        $ticket->conversation->returnToBot(closeAll: true);
    }

    /**
     * Tiket menunggu yang tidak terlayani setelah N hari kerja ditutup (Kedaluwarsa).
     * Opsi B: pemohon diberi tahu; Opsi A: dicatat saja (AI BalesOtomatis yang membalas pemohon).
     *
     * @return int jumlah tiket kedaluwarsa
     */
    public function expireStale(): int
    {
        $limit = max(1, (int) config('kancil.ticket.expire_business_days', 2));
        $notify = config('kancil.bot_engine') === 'laravel';
        $count = 0;

        ServiceTicket::where('status', ServiceTicket::WAITING)->with('conversation')->fifo()->each(
            function (ServiceTicket $ticket) use ($limit, $notify, &$count) {
                if ($this->hours->businessDaysBetween($ticket->queued_at, now()) < $limit) {
                    return;
                }

                $ticket->update([
                    'status' => ServiceTicket::EXPIRED,
                    'resolved_at' => now(),
                    'close_reason' => "Otomatis: tidak terlayani dalam {$limit} hari kerja.",
                ]);

                $conversation = $ticket->conversation;

                if (in_array($conversation->conversation_mode, [Conversation::MODE_WAITING, Conversation::MODE_HUMAN], true)) {
                    $conversation->returnToBot();
                }

                if ($notify) {
                    $this->messenger->queue($conversation, BotScript::ticketExpired($ticket->ticket_number), 'system',
                        meta: ['codes' => ['LYN-KEDALUWARSA'], 'ticket_id' => $ticket->id]);
                }

                $count++;
            });

        return $count;
    }

    /** Tiket masih terbuka dan tidak sedang dipegang petugas lain (Admin boleh). */
    public function authorize(ServiceTicket $ticket, User $user): void
    {
        if (! $ticket->isOpen()) {
            throw TicketException::closed();
        }

        $this->guardOwnership($ticket->conversation, $user);
        $this->guardTicket($ticket, $user);
    }

    /** Petugas tidak boleh menyerobot percakapan yang sedang dipegang petugas lain; Admin boleh. */
    public function guardOwnership(Conversation $conversation, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if ($conversation->isHumanHandling() && $conversation->assigned_to && $conversation->assigned_to !== $user->id) {
            throw TicketException::takenByOther($conversation->assignedPetugas?->name);
        }
    }

    private function withQueueNumber(callable $create): ServiceTicket
    {
        $date = now()->setTimezone(BusinessHours::TIMEZONE)->toDateString();

        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(function () use ($create, $date) {
                    $no = (int) ServiceTicket::whereDate('queue_date', $date)->max('queue_no') + 1;

                    return $create($date, $no);
                });
            } catch (QueryException $e) {
                if ($attempt >= 4 || ! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }
    }
}
