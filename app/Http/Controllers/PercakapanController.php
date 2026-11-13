<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PercakapanController extends Controller
{
    private const LIMIT = 100;

    public function index(Request $request)
    {
        return view('percakapan.index', [
            'threads' => $this->threadList(),
            'counts' => $this->counts(),
            'selectedId' => $request->integer('id') ?: null,
            'canReply' => $request->user()->hasRole('admin', 'petugas'),
        ]);
    }

    /** Polling daftar percakapan. */
    public function threads(): JsonResponse
    {
        return response()->json(['threads' => $this->threadList(), 'counts' => $this->counts()]);
    }

    /** Isi percakapan; pesan pemohon ditandai sudah dibaca. */
    public function messages(Conversation $conversation): JsonResponse
    {
        $conversation->messages()->where('sender_type', 'user')->where('is_read', false)->update(['is_read' => true]);

        $messages = $conversation->messages()
            ->with(['sender:id,name', 'faq:id,code'])
            ->orderBy('sent_at')->orderBy('id')
            ->get()
            ->map(fn (Message $m) => $this->presentMessage($m, $conversation));

        return response()->json([
            'conversation' => $this->presentThread($conversation->loadCount(['messages as unread_count' => fn ($q) => $q->where('sender_type', 'user')->where('is_read', false)])),
            'messages' => $messages,
        ]);
    }

    /** Petugas membalas dari dashboard; bot otomatis berhenti (ambil alih). */
    public function reply(Request $request, Conversation $conversation, WhatsappMessenger $messenger): JsonResponse
    {
        $validated = $request->validate([
            'pesan' => ['required', 'string', 'max:4000'],
        ], [
            'pesan.required' => 'Pesan wajib diisi.',
            'pesan.max' => 'Pesan maksimal 4.000 karakter.',
        ]);

        $this->assignToOfficer($conversation, $request->user()->id);

        $message = $messenger->queue($conversation, trim($validated['pesan']), 'petugas', $request->user()->id);

        return response()->json([
            'message' => $this->presentMessage($message->fresh(['sender:id,name']), $conversation),
            'conversation' => $this->presentThread($conversation->fresh()),
        ], 201);
    }

    public function takeover(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assignToOfficer($conversation, $request->user()->id);

        return response()->json(['conversation' => $this->presentThread($conversation->fresh())]);
    }

    public function release(Conversation $conversation): JsonResponse
    {
        $conversation->update([
            'conversation_mode' => Conversation::MODE_BOT,
            'bot_paused_until' => null,
            'assigned_to' => null,
        ]);

        $conversation->escalationLogs()->where('status', '!=', 'Selesai')->update(['status' => 'Selesai', 'completed_at' => now()]);

        return response()->json(['conversation' => $this->presentThread($conversation->fresh())]);
    }

    /** Unduh lampiran dari disk privat (hanya pengguna yang login). */
    public function attachment(Message $message): StreamedResponse
    {
        abort_unless($message->attachment_path && Storage::disk(WhatsappMessenger::MEDIA_DISK)->exists($message->attachment_path), 404);

        $name = $message->meta['media_name'] ?? basename($message->attachment_path);

        return Storage::disk(WhatsappMessenger::MEDIA_DISK)->download($message->attachment_path, $name);
    }

    private function assignToOfficer(Conversation $conversation, int $userId): void
    {
        $conversation->update([
            'conversation_mode' => Conversation::MODE_HUMAN,
            'assigned_to' => $userId,
            'bot_paused_until' => now()->addMinutes((int) config('kancil.handoff_minutes', 120)),
        ]);

        $conversation->escalationLogs()->where('status', 'Menunggu')->update([
            'status' => 'Diambil',
            'handled_by' => $userId,
            'taken_at' => now(),
        ]);
    }

    private function threadList(): array
    {
        return Conversation::query()
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_type', 'user')->where('is_read', false)])
            ->addSelect(['last_text' => Message::select('message_text')
                ->whereColumn('conversation_id', 'conversations.id')
                ->orderByDesc('sent_at')->orderByDesc('id')->limit(1)])
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Conversation $c) => $this->presentThread($c))
            ->all();
    }

    private function counts(): array
    {
        $byMode = Conversation::selectRaw('conversation_mode, COUNT(*) as total')->groupBy('conversation_mode')->pluck('total', 'conversation_mode');

        return [
            'all' => (int) $byMode->sum(),
            'bot' => (int) ($byMode[Conversation::MODE_BOT] ?? 0),
            'human' => (int) (($byMode[Conversation::MODE_WAITING] ?? 0) + ($byMode[Conversation::MODE_HUMAN] ?? 0)),
        ];
    }

    private function presentThread(Conversation $c): array
    {
        $name = $c->user_name ?: ($c->whatsapp_number ? self::formatPhone($c->whatsapp_number) : 'Kontak WhatsApp');
        [$badge, $class, $header] = match ($c->conversation_mode) {
            Conversation::MODE_WAITING => ['Menunggu Petugas', 'waiting', 'Perlu Petugas'],
            Conversation::MODE_HUMAN => ['Sedang Ditangani', 'agent', 'Sedang Ditangani'],
            Conversation::MODE_CLOSED => ['Selesai', 'success', 'Selesai'],
            default => ['Bot Terjawab', 'bot', 'Otomatis Bot'],
        };

        return [
            'id' => $c->id,
            'name' => $name,
            'phone' => $c->whatsapp_number ? self::formatPhone($c->whatsapp_number) : ($c->wa_chat_id ?? '-'),
            'initial' => self::initials($name),
            'preview' => Str::limit((string) ($c->last_text ?? ''), 80),
            'time' => self::shortTime($c->last_message_at),
            'mode' => $c->conversation_mode,
            'badge' => $badge,
            'badge_class' => $class,
            'header_badge' => $header,
            'filter' => in_array($c->conversation_mode, [Conversation::MODE_WAITING, Conversation::MODE_HUMAN], true) ? 'human' : 'bot',
            'code' => '#'.$c->displayCode(),
            'unread' => (int) ($c->unread_count ?? 0),
            'paused_until' => $c->bot_paused_until?->format('H:i'),
        ];
    }

    private function presentMessage(Message $m, Conversation $c): array
    {
        $sender = match ($m->sender_type) {
            'user' => $c->user_name ?: 'Pemohon',
            'bot' => 'Bot KANCIL SAPA',
            'ai' => 'AI KANCIL SAPA',
            'petugas' => 'Petugas'.($m->sender?->name ? ': '.$m->sender->name : ''),
            default => 'Sistem',
        };

        $text = $m->message_text;

        if ($text === null && $m->message_type !== 'text') {
            $text = match ($m->message_type) {
                'image' => '[Foto]', 'document' => '[Dokumen]', 'audio' => '[Pesan suara]', default => '[Lampiran]',
            };
        }

        return [
            'id' => $m->id,
            'sender_type' => $m->sender_type,
            'sender' => $sender,
            'text' => (string) $text,
            'time' => $m->sent_at?->setTimezone(config('app.timezone'))->format('H:i'),
            'date' => $m->sent_at ? self::dateLabel($m->sent_at) : null,
            'attachment_url' => $m->attachment_path ? route('lampiran', $m) : null,
            'delivery_status' => $m->delivery_status,
            'delivery_error' => $m->delivery_status === 'failed' ? $m->delivery_error : null,
            'faq_code' => $m->faq?->code,
        ];
    }

    public static function formatPhone(string $digits): string
    {
        if (str_starts_with($digits, '62') && strlen($digits) >= 10) {
            $rest = substr($digits, 2);

            return '+62 '.substr($rest, 0, 3).'-'.rtrim(chunk_split(substr($rest, 3), 4, '-'), '-');
        }

        return $digits;
    }

    private static function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim(preg_replace('/[^\p{L}\s]/u', '', $name))) ?: []));

        if ($parts === []) {
            return 'WA';
        }

        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : mb_substr($parts[0], 1, 1);

        return mb_strtoupper(mb_substr($parts[0], 0, 1).$second);
    }

    private static function shortTime(?Carbon $at): string
    {
        if (! $at) {
            return '';
        }

        return $at->isToday() ? $at->format('H:i') : $at->translatedFormat('d M');
    }

    private static function dateLabel(Carbon $at): string
    {
        if ($at->isToday()) {
            return 'Hari ini, '.$at->translatedFormat('d F Y');
        }

        if ($at->isYesterday()) {
            return 'Kemarin, '.$at->translatedFormat('d F Y');
        }

        return $at->translatedFormat('l, d F Y');
    }
}
