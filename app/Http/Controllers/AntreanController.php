<?php

namespace App\Http\Controllers;

use App\Models\ServiceTicket;
use App\Services\Bot\BotScript;
use App\Services\ComplaintService;
use App\Services\Tickets\TicketQueueService;
use App\Services\Tickets\TicketStats;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Antrean tiket layanan petugas (FIFO): daftar, ambil berikutnya, ambil tiket, selesaikan.
 * Semua role boleh melihat; hanya Admin dan Petugas yang boleh bertindak.
 */
class AntreanController extends Controller
{
    private const HISTORY_LIMIT = 50;

    public function __construct(
        private readonly TicketQueueService $tickets,
        private readonly TicketStats $stats,
    ) {}

    public function index(Request $request)
    {
        return view('antrean.index', [
            ...$this->payload($request),
            'canAct' => $request->user()->hasRole('admin', 'petugas'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function claimNext(Request $request): JsonResponse
    {
        $ticket = $this->tickets->claimNext($request->user());

        if (! $ticket) {
            return response()->json(['message' => 'Antrean kosong.'], 404);
        }

        return $this->claimed($ticket);
    }

    public function claim(Request $request, ServiceTicket $ticket): JsonResponse
    {
        $this->tickets->claim($ticket, $request->user());

        return $this->claimed($ticket->fresh());
    }

    public function resolve(Request $request, ServiceTicket $ticket): JsonResponse
    {
        $validated = $request->validate(['kirim_pesan' => ['sometimes', 'boolean']]);

        $ticket = $this->tickets->resolve($ticket, $request->user(), (bool) ($validated['kirim_pesan'] ?? true));

        return response()->json(['ticket' => $this->present($ticket, null)]);
    }

    /**
     * Alihkan tiket antrean menjadi pengaduan resmi (ADU): pengaduan dibuat dari data tiket,
     * tiket antrean ditutup, dan pemohon menerima nomor pengaduan.
     */
    public function toComplaint(Request $request, ServiceTicket $ticket, ComplaintService $complaints, WhatsappMessenger $messenger): JsonResponse
    {
        $validated = $request->validate([
            'kategori' => ['required', Rule::in(array_keys(BotScript::COMPLAINT_CATEGORIES))],
            'isi' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'kategori.required' => 'Kategori pengaduan wajib dipilih.',
            'kategori.in' => 'Kategori pengaduan tidak valid.',
            'isi.required' => 'Isi pengaduan wajib diisi.',
            'isi.min' => 'Isi pengaduan minimal 10 karakter.',
            'isi.max' => 'Isi pengaduan maksimal 5.000 karakter.',
        ]);

        $user = $request->user();
        $this->tickets->authorize($ticket, $user);

        $complaint = DB::transaction(function () use ($ticket, $user, $validated, $complaints) {
            $complaint = $complaints->createFromTicket($ticket, $user, $validated['kategori'], trim($validated['isi']));
            $this->tickets->resolve($ticket, $user, notify: false, reason: "Dialihkan ke pengaduan {$complaint->ticket_number}.");

            return $complaint;
        });

        $messenger->queue($ticket->conversation, BotScript::ticketToComplaint($ticket->ticket_number, $complaint->ticket_number),
            'system', $user->id, meta: ['codes' => ['LYN-ADU', 'PGD-08'], 'ticket_id' => $ticket->id, 'complaint_id' => $complaint->id]);

        return response()->json([
            'ticket' => $this->present($ticket->fresh(), null),
            'pengaduan' => $complaint->ticket_number,
            'pengaduan_url' => route('pengaduan'),
        ], 201);
    }

    private function claimed(ServiceTicket $ticket): JsonResponse
    {
        return response()->json([
            'ticket' => $this->present($ticket, null),
            'percakapan_url' => route('percakapan', ['id' => $ticket->conversation_id]),
        ]);
    }

    private function payload(Request $request): array
    {
        $positions = $this->tickets->positions();
        $relations = ['conversation:id,user_name,whatsapp_number,wa_chat_id', 'officer:id,name'];

        $open = ServiceTicket::open()->with($relations)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ServiceTicket::WAITING])
            ->fifo()
            ->get();

        $history = ServiceTicket::whereNotIn('status', ServiceTicket::OPEN)->with($relations)
            ->where('resolved_at', '>=', now()->startOfDay())
            ->orderByDesc('resolved_at')->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        return [
            'tickets' => $open->map(fn (ServiceTicket $t) => $this->present($t, $positions, $request))->values()->all(),
            'history' => $history->map(fn (ServiceTicket $t) => $this->present($t, $positions, $request))->values()->all(),
            'stats' => $this->stats->today(),
        ];
    }

    /** @param  array<int, int>|null  $positions */
    private function present(ServiceTicket $t, ?array $positions, ?Request $request = null): array
    {
        $conversation = $t->conversation;
        $name = $conversation?->user_name
            ?: ($conversation?->whatsapp_number ? PercakapanController::formatPhone($conversation->whatsapp_number) : 'Kontak WhatsApp');
        $userId = $request?->user()?->id;

        return [
            'id' => $t->id,
            'nomor' => $t->ticket_number,
            'antrean' => $t->queue_no,
            'posisi' => $positions !== null ? ($positions[$t->id] ?? null) : $this->tickets->position($t),
            'nama' => $name,
            'telepon' => $conversation?->whatsapp_number ? PercakapanController::formatPhone($conversation->whatsapp_number) : ($conversation?->wa_chat_id ?? '-'),
            'topik' => BotScript::topicLabel($t->menu_path),
            'kode' => $t->escalation_code,
            'prioritas' => $t->priority,
            'pertanyaan' => $t->question_text ? Str::limit($t->question_text, 200) : null,
            'pertanyaan_lengkap' => $t->question_text,
            'status' => $t->status,
            'petugas' => $t->officer?->name,
            'milik_saya' => $userId !== null && $t->assigned_to === $userId,
            'masuk' => $t->queued_at?->format('H:i'),
            'masuk_tanggal' => $t->queued_at ? self::dateLabel($t->queued_at) : null,
            'menunggu_menit' => $t->queued_at ? (int) $t->queued_at->diffInMinutes($t->taken_at ?? $t->resolved_at ?? now()) : null,
            'ditutup' => $t->resolved_at?->format('H:i'),
            'alasan' => $t->close_reason,
            'percakapan_url' => route('percakapan', ['id' => $t->conversation_id]),
            'ambil_url' => route('antrean.ambil', $t),
            'selesai_url' => route('antrean.selesai', $t),
            'pengaduan_url' => route('antrean.pengaduan', $t),
        ];
    }

    private static function dateLabel(Carbon $at): string
    {
        return $at->isToday() ? 'Hari ini' : ($at->isYesterday() ? 'Kemarin' : $at->translatedFormat('d M Y'));
    }
}
