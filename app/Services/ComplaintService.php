<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Bot\BotScript;
use App\Services\Bot\BusinessHours;
use App\Services\Bot\EscalationRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Tiket pengaduan ADU-YYYYMM-0001, prioritas dan level eskalasi (Matriks Eskalasi 5.3).
 */
class ComplaintService
{
    /** Batas waktu (hari kerja): [respon awal, penyelesaian, naik ke Pimpinan]. */
    public const DEADLINES = [
        'Tinggi' => [1, 3, 6],
        'Sedang' => [1, 5, 10],
        'Rendah' => [2, 7, 14],
    ];

    public function __construct(
        private readonly EscalationRecorder $escalations,
        private readonly BusinessHours $hours,
    ) {}

    /**
     * @param  array{name: string, category: string, text: string, attachments?: list<array<string, mixed>>}  $draft
     */
    public function createFromBot(Conversation $conversation, array $draft): Complaint
    {
        return $this->create($conversation, $draft);
    }

    /**
     * Petugas mengalihkan tiket antrean (LYN) menjadi pengaduan resmi (ADU): pemohon tidak perlu mengisi ulang.
     */
    public function createFromTicket(ServiceTicket $ticket, User $officer, string $category, string $text): Complaint
    {
        $conversation = $ticket->conversation;

        $complaint = $this->create($conversation, [
            'name' => $conversation->user_name ?: 'Pemohon WhatsApp',
            'category' => $category,
            'text' => $text,
            'attachments' => [],
        ], "dari antrean {$ticket->ticket_number}");

        $complaint->update(['assigned_to' => $officer->id]);

        return $complaint;
    }

    /** Log B1 mengikuti status tiket pengaduan (Menunggu / Diambil / Selesai). */
    public function syncEscalationLog(Complaint $complaint, ?int $userId = null): void
    {
        $status = ['Menunggu' => 'Menunggu', 'Diproses' => 'Diambil', 'Selesai' => 'Selesai'][$complaint->status];

        EscalationLog::where('escalation_code', 'B1')
            ->where('conversation_id', $complaint->conversation_id)
            ->where('reason', 'like', $complaint->ticket_number.'%')
            ->get()
            ->each(fn (EscalationLog $log) => $log->update([
                'status' => $status,
                'handled_by' => $status === 'Menunggu' ? $log->handled_by : ($log->handled_by ?? $userId),
                'taken_at' => $status === 'Menunggu' ? $log->taken_at : ($log->taken_at ?? now()),
                'completed_at' => $status === 'Selesai' ? ($log->completed_at ?? now()) : null,
            ]));
    }

    /** @param  array{name: string, category: string, text: string, attachments?: list<array<string, mixed>>}  $draft */
    private function create(Conversation $conversation, array $draft, ?string $note = null): Complaint
    {
        [$categoryName, $priority] = BotScript::COMPLAINT_CATEGORIES[$draft['category']];
        $category = ComplaintCategory::firstOrCreate(['name' => $categoryName], ['is_active' => true]);
        $number = $conversation->whatsapp_number ?: mb_substr((string) $conversation->wa_chat_id, 0, 25);

        $repeat = Complaint::where('whatsapp_number', $number)
            ->where('submitted_at', '>=', now()->subDays(30))
            ->exists();

        $complaint = $this->withTicketNumber(fn (string $ticket) => Complaint::create([
            'ticket_number' => $ticket,
            'conversation_id' => $conversation->id,
            'category_id' => $category->id,
            'user_name' => mb_substr($draft['name'], 0, 100),
            'whatsapp_number' => $number,
            'complaint_text' => $draft['text'],
            'status' => 'Menunggu',
            'priority' => $priority,
            // Kategori 3 (pelayanan petugas) dan pengaduan berulang langsung ke Pimpinan.
            'escalation_level' => ($draft['category'] === '3' || $repeat) ? 2 : 1,
            'attachments' => $draft['attachments'] ?? [],
            'submitted_at' => now(),
        ]));

        $this->escalations->record(
            $conversation,
            'B1',
            "{$complaint->ticket_number} - {$categoryName}".($repeat ? ' (berulang)' : '').($note ? " ({$note})" : ''),
            priority: $priority,
        );

        return $complaint;
    }

    public function nextTicketNumber(): string
    {
        $prefix = 'ADU-'.now()->format('Ym').'-';
        $last = Complaint::where('ticket_number', 'like', $prefix.'%')->orderByDesc('ticket_number')->value('ticket_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Naikkan tiket yang melewati batas ke Pimpinan (Level 2).
     *
     * @return int jumlah tiket yang dinaikkan
     */
    public function escalateOverdue(): int
    {
        $count = 0;

        Complaint::where('status', '!=', 'Selesai')
            ->where('escalation_level', 1)
            ->whereNotNull('priority')
            ->each(function (Complaint $complaint) use (&$count) {
                if ($this->isOverdue($complaint, 2)) {
                    $complaint->update(['escalation_level' => 2]);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Apakah batas waktu terlewati. $stage: 0 = respon awal, 1 = penyelesaian, 2 = naik ke Pimpinan.
     */
    public function isOverdue(Complaint $complaint, int $stage = 1): bool
    {
        if (! $complaint->priority || ! $complaint->submitted_at || $complaint->status === 'Selesai') {
            return false;
        }

        if ($stage === 0 && $complaint->status !== 'Menunggu') {
            return false;
        }

        $limit = self::DEADLINES[$complaint->priority][$stage];

        return $this->hours->businessDaysBetween($complaint->submitted_at, now()) > $limit;
    }

    private function withTicketNumber(callable $create): Complaint
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(fn () => $create($this->nextTicketNumber()));
            } catch (QueryException $e) {
                if ($attempt >= 4 || ! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }
    }
}
