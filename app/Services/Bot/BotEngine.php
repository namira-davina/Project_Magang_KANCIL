<?php

namespace App\Services\Bot;

use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Message;
use App\Models\ServiceTicket;
use App\Services\ComplaintService;
use App\Services\Llm\LlmAnswer;
use App\Services\Llm\LlmResponder;
use App\Services\Tickets\TicketQueueService;
use Carbon\CarbonInterface;

/**
 * Otak bot (Opsi B): menu rule-based sesuai Dokumen Skenario FAQ, alur pengaduan PGD,
 * pencatatan eskalasi sesuai Matriks Eskalasi, dan AI untuk pertanyaan bebas.
 *
 * Status sesi disimpan di conversations.session_state:
 *  menu        kode menu yang sedang aktif (M-00, M-01, M-08)
 *  pgd         langkah pengaduan (PGD-01..PGD-09) atau null
 *  status      menunggu nomor permohonan (Cek Status Paspor)
 *  answer      jalur jawaban terakhir yang dibuka (mis. 0>7) atau null
 *  shown       jawaban terakhir benar-benar tampil (bukan MSG-05)
 *  invalid     jumlah salah input berturut-turut
 *  after_msg03 MSG-03 baru dikirim (88 berikutnya tidak dicatat dua kali)
 *  complaint   draf pengaduan
 */
class BotEngine
{
    private const GREETINGS = [
        'halo', 'hallo', 'helo', 'hai', 'hay', 'hi', 'hello', 'pagi', 'siang', 'sore', 'malam', 'selamat',
        'assalamualaikum', 'assalamu', 'alaikum', 'assalamu\'alaikum', 'salam', 'permisi', 'min', 'admin', 'kak',
        'pak', 'bu', 'mas', 'mbak', 'p', 'tes', 'test', 'ping', 'om', 'swastiastu', 'shalom', 'good', 'morning',
        'afternoon', 'evening', 'wr', 'wb', 'warahmatullahi', 'wabarakatuh',
    ];

    private array $state = [];

    private Conversation $conversation;

    private Message $incoming;

    private ?CarbonInterface $previousInboundAt = null;

    public function __construct(
        private readonly LlmResponder $llm,
        private readonly EscalationRecorder $escalations,
        private readonly BusinessHours $hours,
        private readonly ComplaintService $complaints,
        private readonly TicketQueueService $tickets,
        private readonly MenuAnswerResolver $answers,
    ) {}

    /**
     * Proses satu pesan masuk. $previousInboundAt = waktu pesan masuk sebelumnya (untuk batas sesi).
     *
     * @return list<BotReply>
     */
    public function handle(Conversation $conversation, Message $incoming, ?CarbonInterface $previousInboundAt = null): array
    {
        $this->conversation = $conversation;
        $this->incoming = $incoming;
        $this->previousInboundAt = $previousInboundAt;
        $this->state = $conversation->session_state ?? [];

        $replies = $this->route(trim((string) $incoming->message_text));

        $conversation->session_state = $this->state;
        $conversation->current_menu = $this->state['pgd'] ?? $this->state['menu'] ?? null;
        $conversation->save();

        return $replies;
    }

    /** @return list<BotReply> */
    private function route(string $text): array
    {
        $isNew = $this->state === [];
        $expired = ! $isNew && $this->isExpired();

        if ($isNew || $expired) {
            return $this->startSession($text, $expired);
        }

        if ($this->isMedia()) {
            return $this->handleMedia();
        }

        $command = self::normalize($text);

        if ($this->state['pgd'] ?? null) {
            return $this->complaintStep($command, $text);
        }

        if ($this->state['status'] ?? false) {
            return $this->statusStep($command, $text);
        }

        if ($this->state['answer'] ?? null) {
            return $this->answerAction($command, $text);
        }

        return match (true) {
            in_array($command, ['0', '99', 'menu'], true) => [$this->goMainMenu()],
            $command === '88' => [$this->escalate(viaMenu: false)],
            ctype_digit($command) => $this->selectOption($command, $text),
            $this->isGreeting($text) => [$this->goMainMenu()],
            default => $this->freeText($text),
        };
    }

    /**
     * Setelah jawaban tampil (persis Bogor): 0 = menu utama, 1 = hubungi Petugas.
     * 99 / 88 / menu tetap diterima sebagai jalan pintas.
     *
     * @return list<BotReply>
     */
    private function answerAction(string $command, string $text): array
    {
        return match (true) {
            in_array($command, ['0', '99', 'menu'], true) => [$this->goMainMenu()],
            in_array($command, ['1', '88'], true) => [$this->escalate(viaMenu: false)],
            ctype_digit($command) => [$this->invalidInput($text)],
            $this->isGreeting($text) => [$this->goMainMenu()],
            default => $this->freeText($text),
        };
    }

    // ---------------------------------------------------------------- sesi

    private function isExpired(): bool
    {
        $last = $this->previousInboundAt;

        return $last !== null && $last->lt(now()->subMinutes((int) config('kancil.session_timeout_minutes', 30)));
    }

    /** @return list<BotReply> */
    private function startSession(string $text, bool $expired): array
    {
        $hadDraft = $expired && $this->hasComplaintDraft();
        $this->state = self::freshState();

        if ($hadDraft) {
            $this->escalations->record($this->conversation, 'B0', 'Sesi berakhir sebelum pengaduan dikirim.');

            return [$this->reply(BotScript::msg('MSG-09')."\n\n".$this->mainMenuText(), ['MSG-09', 'M-00'])];
        }

        if (! $this->isMedia() && $this->isQuestionLike($text)) {
            $answer = $this->llm->answer($this->conversation, $text, $this->incoming);

            if ($answer?->confident) {
                return [$this->aiReply($answer)];
            }
        }

        if ($expired) {
            return [$this->reply(BotScript::msg('MSG-06')."\n\n".$this->mainMenuText(), ['MSG-06', 'M-00'])];
        }

        return [$this->reply($this->mainMenuText(), ['M-00'])];
    }

    private static function freshState(): array
    {
        return ['menu' => 'M-00', 'pgd' => null, 'status' => false, 'queued' => false, 'answer' => null, 'shown' => false, 'invalid' => 0, 'after_msg03' => false, 'complaint' => null];
    }

    // ---------------------------------------------------------------- menu

    private function goMainMenu(): BotReply
    {
        $this->state = self::freshState();

        return $this->reply($this->mainMenuText(), ['M-00']);
    }

    /** "Kembali ke menu sebelumnya" dari submenu: ke menu induk (menu utama lengkap dengan sapaan dan status hari ini). */
    private function back(): BotReply
    {
        $this->validInput();
        $this->state['status'] = false;

        $parent = BotScript::MENUS[$this->state['menu']]['parent'] ?? null;

        return $this->showMenu($parent ?? 'M-00');
    }

    /** @return list<BotReply> */
    private function selectOption(string $key, string $raw): array
    {
        $option = BotScript::MENUS[$this->state['menu']]['options'][$key] ?? null;

        if (! $option) {
            return [$this->invalidInput($raw)];
        }

        $this->validInput();
        $target = $option[1];

        if (str_starts_with($target, 'M-')) {
            $this->state['answer'] = null;

            return [$this->showMenu($target)];
        }

        if (str_starts_with($target, 'ANS:')) {
            return [$this->showAnswer(substr($target, 4))];
        }

        if ($target === 'BACK') {
            return [$this->back()];
        }

        if (str_starts_with($target, 'STATUS:')) {
            $this->state['status'] = true;
            $this->state['answer'] = null;

            return [$this->reply(BotScript::statusPrompt(), ['STATUS'])];
        }

        if ($target === 'PGD-01') {
            $this->state['pgd'] = 'PGD-01';
            $this->state['answer'] = null;

            return [$this->reply(BotScript::pgd('PGD-01'), ['PGD-01'])];
        }

        return [$this->escalate(viaMenu: true, path: substr($target, 4))];
    }

    private function showMenu(string $code): BotReply
    {
        $this->state['menu'] = $code;
        $this->state['answer'] = null;
        $this->state['status'] = false;

        if ($code === 'M-00') {
            return $this->reply($this->mainMenuText(), ['M-00']);
        }

        return $this->reply($this->menuText($code), [$code]);
    }

    private function menuText(string $code): string
    {
        return BotScript::menu($code);
    }

    private function mainMenuText(): string
    {
        return BotScript::mainMenu($this->conversation->user_name, $this->hours->statusText());
    }

    /** Jawaban menu dari FAQ sumber; FAQ belum Aktif = Pesan 5 (tetap dengan pilihan 0 = menu, 1 = petugas). */
    private function showAnswer(string $path): BotReply
    {
        $answer = $this->answers->resolve($path);

        $this->state['answer'] = $path;
        $this->state['shown'] = ! $answer['held'];

        if ($answer['held']) {
            return $this->reply($answer['text']."\n\n".BotScript::answerFooter(), ['MSG-05', 'ANS-'.$path]);
        }

        $this->tagIncoming($answer['faq_id']);

        return $this->reply(BotScript::answer($answer['text']), ['ANS-'.$path, ...$answer['codes']], $answer['faq_id']);
    }

    /**
     * Cek Status Paspor: pemohon mengetik nomor permohonan; hasilnya dibalas petugas lewat antrean
     * (kantor Cilacap tidak punya akses otomatis ke sistem status paspor).
     *
     * @return list<BotReply>
     */
    private function statusStep(string $command, string $text): array
    {
        if (in_array($command, ['0', '99', 'menu'], true)) {
            return [$this->goMainMenu()];
        }

        $number = preg_replace('/\s+/', '', $text);

        if (! preg_match('/^\d{10,25}$/', $number)) {
            return [$this->invalidInput($text)];
        }

        $log = $this->escalations->record($this->conversation, 'A2', "Cek status paspor, no. permohonan {$number}.", null, '0>10');
        $ticket = $this->enqueue($log, "Cek status paspor, no. permohonan {$number}");

        return [$this->reply($this->handoffText($ticket), ['STATUS', 'LYN'])];
    }

    // ---------------------------------------------------------------- eskalasi

    private function escalate(bool $viaMenu, ?string $path = null): BotReply
    {
        if ($viaMenu) {
            $log = $this->escalations->record($this->conversation, 'A2', 'Memilih menu layanan petugas: '.BotScript::topicLabel($path).'.', null, $path);
        } elseif (! ($this->state['after_msg03'] ?? false)) {
            $path = $this->state['answer'] ?? null;
            $special = ($path && ($this->state['shown'] ?? false))
                ? ($path === 'AI' ? ($this->state['ai_code'] ?? null) : (BotScript::ANSWERS[$path]['code'] ?? null))
                : null;

            if ($special) {
                $log = $this->escalations->record($this->conversation, $special, "Menghubungi petugas setelah jawaban {$path}.", null, $path);
            } else {
                $log = $this->escalations->record($this->conversation, 'A2', 'Menghubungi petugas.', null, $path ?? BotScript::MENUS[$this->state['menu']]['path']);
            }
        } else {
            // Petugas setelah MSG-03: log A1 sudah dicatat saat MSG-03.
            $log = $this->conversation->escalationLogs()->latest('id')->first();
        }

        $ticket = $this->enqueue($log, $this->recentQuestion());

        return $this->reply($this->handoffText($ticket), ['ESK-01', 'LYN']);
    }

    /**
     * Masuk antrean petugas: sesi menu dimulai ulang (setelah tiket selesai, pesan berikutnya kembali ke menu utama),
     * bot diam, dan tiket antrean dibuat (atau tiket terbuka yang ada dipakai lagi).
     */
    private function enqueue(?EscalationLog $log, ?string $question): ServiceTicket
    {
        $this->state = self::freshState();
        $this->state['queued'] = true;
        $this->markWaitingHuman();

        return $this->tickets->open($this->conversation, $log, $question)[0];
    }

    /** Pertanyaan terakhir pemohon (bukan angka menu/salam) dalam sesi ini, untuk konteks petugas. */
    private function recentQuestion(): ?string
    {
        return $this->conversation->messages()
            ->where('sender_type', 'user')->where('message_type', 'text')
            ->where('id', '!=', $this->incoming->id ?? 0)
            ->where('sent_at', '>=', now()->subMinutes((int) config('kancil.session_timeout_minutes', 30)))
            ->orderByDesc('sent_at')->orderByDesc('id')
            ->limit(10)
            ->pluck('message_text')
            ->first(fn ($text) => $this->isQuestionText((string) $text));
    }

    private function isQuestionText(string $text): bool
    {
        $command = self::normalize($text);

        return ! ctype_digit($command) && ! in_array($command, ['menu', 'batal', 'selesai'], true)
            && count($this->words($text)) >= 2 && ! $this->isGreeting($text);
    }

    /** Pesan eskalasi: nomor tiket, posisi antrean, dan pilihan selama menunggu. */
    private function handoffText(ServiceTicket $ticket): string
    {
        return BotScript::handoff($ticket->queue_no, $this->tickets->position($ticket));
    }

    /** Masuk antrean petugas: bot diam sampai tiket diambil, diselesaikan, dibatalkan, atau kedaluwarsa. */
    private function markWaitingHuman(): void
    {
        if (in_array($this->conversation->conversation_mode, [Conversation::MODE_BOT, Conversation::MODE_CLOSED, null], true)) {
            $this->conversation->conversation_mode = Conversation::MODE_WAITING;
            $this->conversation->bot_paused_until = null;
        }
    }

    /**
     * Pesan pemohon selama tiket terbuka. Bot tidak pernah diam tanpa pilihan:
     *  petugas sudah mengambil -> pengingat singkat (dibatasi)
     *  mode antrean (state.queued): 0 = menu utama, 1 = cek posisi, 2 = batal; pesan lain = pengingat + pilihan
     *  mode menu (setelah 0): menu bot berjalan normal, tiket tetap terbuka; eskalasi lagi memakai tiket yang sama
     *
     * @return list<BotReply>
     */
    public function handleQueued(Conversation $conversation, Message $incoming, ?CarbonInterface $previousInboundAt = null): array
    {
        $this->conversation = $conversation;
        $this->incoming = $incoming;
        $ticket = $conversation->serviceTickets()->open()->latest('id')->first();

        if (! $ticket) {
            return [];
        }

        $handling = $ticket->status === ServiceTicket::HANDLING;
        $queueMode = ($conversation->session_state['queued'] ?? true) === true;

        if (! $handling && ! $queueMode) {
            return $this->handle($conversation, $incoming, $previousInboundAt);
        }

        $command = $incoming->message_type === 'text' ? self::normalize((string) $incoming->message_text) : '';
        $waiting = $ticket->status === ServiceTicket::WAITING;

        if ($waiting && in_array($command, ['2', 'batal', 'batalkan', 'batal antrean', 'batal antrian', 'cancel'], true)) {
            $this->tickets->cancel($ticket);
            $this->state = self::freshState();
            $conversation->forceFill(['session_state' => $this->state, 'current_menu' => 'M-00'])->save();

            return [$this->reply(BotScript::queueCancelled()."\n\n".$this->mainMenuText(), ['LYN-BATAL', 'M-00'])];
        }

        if ($waiting && in_array($command, ['0', '99', 'menu'], true)) {
            $this->state = self::freshState();
            $conversation->forceFill(['session_state' => $this->state, 'current_menu' => 'M-00'])->save();

            return [$this->reply($this->mainMenuText(), ['M-00'])];
        }

        $asked = $command === '1' || (bool) preg_match('/^(cek\s+)?(antrean|antrian|posisi|status)(\s+(saya|antrean|antrian))?$/u', $command);
        $seconds = $asked ? 10 : max(5, (int) config('kancil.ticket.notice_seconds', 30));

        if ($ticket->last_notice_at && $ticket->last_notice_at->gt(now()->subSeconds($seconds))) {
            return [];
        }

        $ticket->update(['last_notice_at' => now()]);

        return [$this->reply(
            BotScript::queueStatus($ticket->queue_no, $this->tickets->position($ticket), $handling),
            ['LYN-STATUS'],
        )];
    }

    // ---------------------------------------------------------------- input salah & teks bebas

    private function validInput(): void
    {
        $this->state['invalid'] = 0;
        $this->state['after_msg03'] = false;
    }

    private function invalidInput(string $raw): BotReply
    {
        $this->state['invalid'] = ($this->state['invalid'] ?? 0) + 1;

        if ($this->state['invalid'] >= 3) {
            $this->state['invalid'] = 0;
            $this->state['after_msg03'] = true;

            if ($this->state['pgd'] ?? null) {
                $this->discardComplaint('Salah input 3 kali pada langkah pengaduan.');
            } else {
                $this->escalations->record($this->conversation, 'A1', $raw, null,
                    $this->state['answer'] ?? BotScript::MENUS[$this->state['menu']]['path']);
            }

            return $this->reply(BotScript::msg('MSG-03'), ['MSG-03']);
        }

        $id = $this->state['invalid'] === 1 ? 'MSG-01' : 'MSG-02';

        return $this->reply(BotScript::msg($id)."\n\n".$this->currentPrompt(), [$id, $this->currentPromptCode()]);
    }

    /** @return list<BotReply> */
    private function freeText(string $text): array
    {
        if ($this->llm->isAvailable()) {
            if ($this->isGreeting($text)) {
                return [$this->goMainMenu()];
            }

            $answer = $this->llm->answer($this->conversation, $text, $this->incoming);

            if ($answer?->confident) {
                return [$this->aiReply($answer)];
            }

            if ($answer !== null) {
                $log = $this->escalations->record($this->conversation, 'A1', 'AI: '.$text, null, 'AI');
                $ticket = $this->enqueue($log, $text);

                return [$this->reply(BotScript::msg('AI-UNKNOWN')."\n\n".$this->handoffText($ticket), ['AI-UNKNOWN', 'ESK-01', 'LYN'], sender: 'ai')];
            }
        }

        return [$this->invalidInput($text)];
    }

    private function aiReply(LlmAnswer $answer): BotReply
    {
        $this->validInput();
        $this->state['answer'] = 'AI';
        $this->state['ai_code'] = BotScript::FAQ_ESCALATION[$answer->faqCodes[0]] ?? null;
        $this->state['shown'] = true;
        $this->tagIncoming($answer->primaryFaqId());

        $text = $answer->text;

        if ($answer->usedUnverified) {
            $text .= "\n\n".BotScript::msg('AI-DISCLAIMER');
        }

        return $this->reply(BotScript::answer($text), ['AI', ...$answer->faqCodes], $answer->primaryFaqId(), 'ai');
    }

    // ---------------------------------------------------------------- media

    /** @return list<BotReply> */
    private function handleMedia(): array
    {
        if (($this->state['pgd'] ?? null) === 'PGD-06') {
            return [$this->attach()];
        }

        return [$this->reply(BotScript::msg('MSG-04')."\n\n".$this->currentPrompt(), ['MSG-04', $this->currentPromptCode()])];
    }

    private function isMedia(): bool
    {
        return $this->incoming->message_type !== 'text';
    }

    // ---------------------------------------------------------------- pengaduan (PGD-01..10)

    /** @return list<BotReply> */
    private function complaintStep(string $command, string $text): array
    {
        $step = $this->state['pgd'];

        if ($command === 'batal') {
            return [$this->cancelComplaint()];
        }

        if (in_array($command, ['0', '99', 'menu'], true)) {
            $this->discardComplaint('Kembali ke menu utama saat mengisi pengaduan.');

            return [$this->goMainMenu()];
        }

        if ($command === '88') {
            $this->discardComplaint('Menghubungi petugas saat mengisi pengaduan.');

            return [$this->escalate(viaMenu: false)];
        }

        if ($command === '9') {
            return [$this->complaintBack($step)];
        }

        return [match ($step) {
            'PGD-01' => match ($command) {
                '1' => $this->toStep('PGD-02', ['complaint' => ['name' => null, 'category' => null, 'text' => null, 'attachments' => []]]),
                '2' => $this->toStep('PGD-09'),
                default => $this->invalidInput($text),
            },
            'PGD-02' => $this->setName($text),
            'PGD-03' => isset(BotScript::COMPLAINT_CATEGORIES[$command])
                ? $this->toStep('PGD-04', ['complaint.category' => $command])
                : $this->invalidInput($text),
            'PGD-04' => mb_strlen($text) < (int) config('kancil.complaint.min_length', 20)
                ? $this->reply(BotScript::pgd('PGD-04-SHORT'), ['PGD-04'])
                : $this->toStep('PGD-05', ['complaint.text' => mb_substr($text, 0, 5000)]),
            'PGD-05' => match ($command) {
                '1' => $this->toStep('PGD-06'),
                '2' => $this->toStep('PGD-07'),
                default => $this->invalidInput($text),
            },
            'PGD-06' => $command === 'selesai' ? $this->toStep('PGD-07') : $this->invalidInput($text),
            'PGD-07' => match ($command) {
                '1' => $this->submitComplaint(),
                '2' => $this->cancelComplaint(),
                default => $this->invalidInput($text),
            },
            'PGD-09' => $this->checkTicket($text),
            default => $this->goMainMenu(),
        }];
    }

    private function toStep(string $step, array $set = []): BotReply
    {
        $this->validInput();

        foreach ($set as $key => $value) {
            data_set($this->state, $key, $value);
        }

        $this->state['pgd'] = $step;

        return $this->reply($this->pgdPrompt($step), [$step]);
    }

    private function setName(string $text): BotReply
    {
        $name = trim(preg_replace('/\s+/', ' ', $text));

        if ($name === '') {
            return $this->reply(BotScript::pgd('PGD-02'), ['PGD-02']);
        }

        return $this->toStep('PGD-03', ['complaint.name' => mb_substr($name, 0, 100)]);
    }

    private function complaintBack(string $step): BotReply
    {
        $previous = [
            'PGD-01' => null, 'PGD-09' => 'PGD-01', 'PGD-02' => 'PGD-01', 'PGD-03' => 'PGD-02',
            'PGD-04' => 'PGD-03', 'PGD-05' => 'PGD-04', 'PGD-06' => 'PGD-05', 'PGD-07' => 'PGD-05',
        ][$step] ?? null;

        if ($previous === null) {
            $this->state['pgd'] = null;
            $this->state['complaint'] = null;
            $this->validInput();

            return $this->showMenu('M-00');
        }

        if ($previous === 'PGD-01') {
            $this->state['complaint'] = null;
        }

        return $this->toStep($previous);
    }

    private function attach(): BotReply
    {
        $max = (int) config('kancil.complaint.max_attachments', 3);
        $attachments = $this->state['complaint']['attachments'] ?? [];

        if (count($attachments) >= $max) {
            return $this->reply(BotScript::pgd('PGD-06-FULL'), ['PGD-06']);
        }

        $attachments[] = [
            'message_id' => $this->incoming->id,
            'path' => $this->incoming->attachment_path,
            'type' => $this->incoming->message_type,
            'name' => $this->incoming->meta['media_name'] ?? null,
        ];
        $this->state['complaint']['attachments'] = $attachments;
        $this->validInput();

        return $this->reply(BotScript::pgd('PGD-06-ACK', ['n' => count($attachments)]), ['PGD-06']);
    }

    private function submitComplaint(): BotReply
    {
        $complaint = $this->complaints->createFromBot($this->conversation, $this->state['complaint']);
        $this->state = self::freshState();

        return $this->reply(BotScript::pgd('PGD-08', ['ticket' => $complaint->ticket_number]), ['PGD-08']);
    }

    private function cancelComplaint(): BotReply
    {
        $this->discardComplaint('Pengaduan dibatalkan pengguna.');
        $this->state = self::freshState();

        return $this->reply(BotScript::msg('MSG-08')."\n\n".$this->mainMenuText(), ['MSG-08', 'M-00']);
    }

    private function discardComplaint(string $reason): void
    {
        if ($this->hasComplaintDraft()) {
            $this->escalations->record($this->conversation, 'B0', $reason);
        }

        $this->state['pgd'] = null;
        $this->state['complaint'] = null;
        $this->state['menu'] = 'M-00';
        $this->state['answer'] = null;
        $this->state['status'] = false;
    }

    private function hasComplaintDraft(): bool
    {
        return in_array($this->state['pgd'] ?? null, ['PGD-02', 'PGD-03', 'PGD-04', 'PGD-05', 'PGD-06', 'PGD-07'], true);
    }

    private function checkTicket(string $text): BotReply
    {
        $this->validInput();
        $ticket = strtoupper(preg_replace('/\s+/', '', $text));

        $complaint = Complaint::where('ticket_number', $ticket)->first();
        $owner = $complaint && (
            $complaint->conversation_id === $this->conversation->id
            || ($this->conversation->whatsapp_number && $complaint->whatsapp_number === $this->conversation->whatsapp_number)
        );

        if (! $owner) {
            return $this->reply(BotScript::pgd('PGD-10-NOTFOUND'), ['PGD-10']);
        }

        return $this->reply(BotScript::pgd('PGD-10', [
            'ticket' => $complaint->ticket_number,
            'status' => Complaint::STATUS_LABEL_FOR_USER[$complaint->status] ?? $complaint->status,
            'response' => $complaint->response_text,
        ]), ['PGD-10']);
    }

    private function pgdPrompt(string $step): string
    {
        if ($step !== 'PGD-07') {
            return BotScript::pgd($step);
        }

        $draft = $this->state['complaint'];

        return BotScript::pgd('PGD-07', [
            'name' => $draft['name'],
            'category' => BotScript::COMPLAINT_CATEGORIES[$draft['category']][0],
            'text' => $draft['text'],
            'attachments' => count($draft['attachments'] ?? []),
        ]);
    }

    // ---------------------------------------------------------------- utilitas

    private function currentPrompt(): string
    {
        if ($step = $this->state['pgd'] ?? null) {
            return $this->pgdPrompt($step);
        }

        if ($this->state['status'] ?? false) {
            return BotScript::statusPrompt();
        }

        if ($this->state['answer'] ?? null) {
            return BotScript::answerFooter();
        }

        return $this->state['menu'] === 'M-00' ? $this->mainMenuText() : $this->menuText($this->state['menu']);
    }

    private function currentPromptCode(): string
    {
        return $this->state['pgd'] ?? $this->state['menu'];
    }

    private function tagIncoming(?int $faqId): void
    {
        if ($faqId && $this->incoming->exists) {
            $this->incoming->update(['faq_id' => $faqId]);
        }
    }

    private function reply(string $text, array $codes, ?int $faqId = null, string $sender = 'bot'): BotReply
    {
        return new BotReply($text, array_values(array_filter($codes)), $faqId, $sender);
    }

    public static function normalize(string $text): string
    {
        return trim(mb_strtolower(preg_replace('/^[\s\p{P}]+|[\s\p{P}]+$/u', '', $text)));
    }

    private function words(string $text): array
    {
        return array_values(array_filter(preg_split('/[^\p{L}\p{N}\']+/u', mb_strtolower($text))));
    }

    private function isGreeting(string $text): bool
    {
        $words = $this->words($text);

        return $words !== [] && array_diff($words, self::GREETINGS) === [];
    }

    private function isQuestionLike(string $text): bool
    {
        $command = self::normalize($text);

        return ! ctype_digit($command) && $command !== 'menu'
            && count($this->words($text)) >= 2 && ! $this->isGreeting($text)
            && $this->llm->isAvailable();
    }
}
