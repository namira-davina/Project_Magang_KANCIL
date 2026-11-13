<?php

namespace App\Services\Bot;

use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Faq;
use App\Models\Message;
use App\Models\ServiceTicket;
use App\Services\ComplaintService;
use App\Services\Llm\LlmAnswer;
use App\Services\Llm\LlmResponder;
use App\Services\Tickets\TicketQueueService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Otak bot (Opsi B): menu rule-based sesuai Dokumen Skenario FAQ, alur pengaduan PGD,
 * pencatatan eskalasi sesuai Matriks Eskalasi, dan AI untuk pertanyaan bebas.
 *
 * Status sesi disimpan di conversations.session_state:
 *  menu        kode menu yang sedang aktif (M-00..M-10)
 *  pgd         langkah pengaduan (PGD-01..PGD-09) atau null
 *  answer      jalur jawaban terakhir yang dibuka (mis. 0>1>5) atau null
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

        if ($this->state['answer'] ?? null) {
            return $this->answerAction($command, $text);
        }

        return match (true) {
            in_array($command, ['0', '99', 'menu'], true) => [$this->goMainMenu()],
            $command === '88' => [$this->escalate(viaMenu3: false)],
            $command === '9' => [$this->back()],
            ctype_digit($command) => $this->selectOption($command, $text),
            $this->isGreeting($text) => [$this->goMainMenu(greet: true)],
            default => $this->freeText($text),
        };
    }

    /**
     * Setelah jawaban tampil (gaya Bogor): 0 = kembali ke menu sebelumnya, 1 = hubungi Petugas.
     * 99 / 88 / 9 tetap diterima sebagai jalan pintas.
     *
     * @return list<BotReply>
     */
    private function answerAction(string $command, string $text): array
    {
        return match (true) {
            in_array($command, ['0', '9'], true) => [$this->back()],
            in_array($command, ['1', '88'], true) => [$this->escalate(viaMenu3: false)],
            in_array($command, ['99', 'menu'], true) => [$this->goMainMenu()],
            ctype_digit($command) => [$this->invalidInput($text)],
            $this->isGreeting($text) => [$this->goMainMenu(greet: true)],
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
            return [$this->reply(BotScript::msg('MSG-06')."\n\n".$this->mainMenuText(greet: true), ['MSG-06', 'M-00'])];
        }

        return [$this->reply($this->mainMenuText(greet: true), ['M-00'])];
    }

    private static function freshState(): array
    {
        return ['menu' => 'M-00', 'pgd' => null, 'answer' => null, 'shown' => false, 'invalid' => 0, 'after_msg03' => false, 'complaint' => null];
    }

    // ---------------------------------------------------------------- menu

    private function goMainMenu(bool $greet = false): BotReply
    {
        $this->state = self::freshState();

        return $this->reply($this->mainMenuText($greet), ['M-00']);
    }

    private function back(): BotReply
    {
        $this->validInput();

        if ($this->state['answer']) {
            $this->state['answer'] = null;

            return $this->showMenu($this->state['menu']);
        }

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

        if ($target === 'PGD-01') {
            $this->state['pgd'] = 'PGD-01';
            $this->state['answer'] = null;

            return [$this->reply(BotScript::pgd('PGD-01'), ['PGD-01'])];
        }

        return [$this->escalate(viaMenu3: true)];
    }

    private function showMenu(string $code): BotReply
    {
        $this->state['menu'] = $code;
        $this->state['answer'] = null;

        if ($code === 'M-00') {
            return $this->reply($this->mainMenuText(), ['M-00']);
        }

        return $this->reply($this->menuText($code), [$code]);
    }

    private function menuText(string $code): string
    {
        $intro = null;
        $introCode = BotScript::MENUS[$code]['intro_faq'] ?? null;

        if ($introCode) {
            $faq = Faq::where('code', $introCode)->first();
            $intro = $faq && $this->canShow($faq) ? "*{$faq->question}*\n{$faq->answer}" : null;
        }

        return BotScript::menu($code, $intro);
    }

    private function mainMenuText(bool $greet = false): string
    {
        return BotScript::mainMenu($this->llm->isAvailable(), $this->conversation->user_name, $greet);
    }

    private function showAnswer(string $path): BotReply
    {
        $definition = BotScript::ANSWERS[$path];
        $faqs = $this->faqsByCode($definition['faqs']);
        $published = $faqs->filter(fn (Faq $faq) => $this->canShow($faq));
        $held = collect($definition['faqs'])->reject(fn ($code) => $published->has($code))->values();

        $this->state['answer'] = $path;
        $this->state['shown'] = $published->isNotEmpty();
        $firstFaqId = $faqs->first()?->id;

        if ($held->isNotEmpty()) {
            $this->escalations->record(
                $this->conversation, 'A3', 'Jawaban ditahan: '.$held->implode(', '),
                $faqs->get($held->first())?->id, $path,
            );
        }

        if ($published->isEmpty()) {
            $this->tagIncoming($firstFaqId);

            return $this->reply(BotScript::msg('MSG-05')."\n\n".BotScript::answerFooter(), ['MSG-05'], $firstFaqId);
        }

        $body = $published->count() === 1 && count($definition['faqs']) === 1
            ? $published->first()->answer
            : $published->map(fn (Faq $faq) => "*{$faq->question}*\n{$faq->answer}")->implode("\n\n");

        if ($held->isNotEmpty()) {
            $body .= "\n\n".BotScript::msg('MSG-05');
        }

        if ($published->contains(fn (Faq $faq) => ! $faq->isPublished())) {
            $body .= "\n\n".BotScript::msg('AI-DISCLAIMER');
        }

        // offer88: tawaran menghubungi petugas kini selalu ada di penutup jawaban (Ketik 1).
        $primary = $published->first()->id;
        $this->tagIncoming($primary);
        $codes = $published->keys()->all();

        return $this->reply(BotScript::answer($body), $held->isNotEmpty() ? [...$codes, 'MSG-05'] : $codes, $primary);
    }

    /**
     * BOT_FAQ_SCOPE=verified (bawaan): hanya FAQ Terverifikasi + Aktif.
     * BOT_FAQ_SCOPE=all: semua FAQ non-draf (sementara, selama verifikasi belum selesai) + catatan "Informasi umum".
     */
    private function canShow(Faq $faq): bool
    {
        return $faq->isPublished()
            || (config('kancil.faq_scope') === 'all' && $faq->verification_status !== 'draft');
    }

    /** @return Collection<string, Faq> urut sesuai daftar kode */
    private function faqsByCode(array $codes): Collection
    {
        $found = Faq::whereIn('code', $codes)->get()->keyBy('code');

        return collect($codes)->filter(fn ($c) => $found->has($c))->mapWithKeys(fn ($c) => [$c => $found[$c]]);
    }

    // ---------------------------------------------------------------- eskalasi

    private function escalate(bool $viaMenu3): BotReply
    {
        $codes = $viaMenu3 ? ['KTK-001', 'ESK-01'] : ['ESK-01'];

        if ($viaMenu3) {
            $log = $this->escalations->record($this->conversation, 'A2', 'Memilih menu 3 (Hubungi Petugas).',
                Faq::where('code', 'KTK-001')->value('id'), '3');
        } elseif (! ($this->state['after_msg03'] ?? false)) {
            $path = $this->state['answer'] ?? null;
            $special = ($path && ($this->state['shown'] ?? false)) ? (BotScript::ANSWERS[$path]['code'] ?? null) : null;

            if ($special) {
                $faqId = Faq::where('code', BotScript::ANSWERS[$path]['faqs'][0])->value('id');
                $log = $this->escalations->record($this->conversation, $special, "Mengetik 88 setelah jawaban {$path}.", $faqId, $path);
            } else {
                $log = $this->escalations->record($this->conversation, 'A2', 'Mengetik 88.', null, $path ?? BotScript::MENUS[$this->state['menu']]['path']);
            }
        } else {
            // 88 setelah MSG-03: log A1 sudah dicatat saat MSG-03.
            $log = $this->conversation->escalationLogs()->latest('id')->first();
        }

        $ticket = $this->enqueue($log, $this->recentQuestion());

        return $this->reply($this->handoffText($codes, $ticket), $codes);
    }

    /**
     * Masuk antrean petugas: sesi menu dimulai ulang (setelah tiket selesai, pesan berikutnya kembali ke menu utama),
     * bot diam, dan tiket antrean dibuat (atau tiket terbuka yang ada dipakai lagi).
     */
    private function enqueue(?EscalationLog $log, ?string $question): ServiceTicket
    {
        $this->state = self::freshState();
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

    /** Pesan antrean petugas (tanpa nomor/email kantor) + nomor tiket/posisi + catatan di luar jam layanan. */
    private function handoffText(array &$codes, ServiceTicket $ticket): string
    {
        $text = BotScript::handoff($ticket->ticket_number, $ticket->queue_no, $this->tickets->position($ticket));
        $codes[] = 'LYN';

        if (config('kancil.outside_hours_notice') && ! $this->hours->isOpen()) {
            $text .= "\n\n".BotScript::msg('MSG-07', $this->hours->publicText());
            $codes[] = 'MSG-07';
        }

        return $text;
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
     * Pesan pemohon selama menunggu di antrean petugas:
     *  "batal"   -> tiket dibatalkan, kembali ke menu utama
     *  "antrean" -> posisi antrean (paling sering 1x per menit)
     *  lainnya   -> pengingat posisi (paling sering 1x per kancil.ticket.notice_minutes); pesan tetap tercatat untuk petugas.
     *
     * @return list<BotReply>
     */
    public function handleQueued(Conversation $conversation, Message $incoming): array
    {
        $this->conversation = $conversation;
        $this->incoming = $incoming;
        $ticket = $conversation->serviceTickets()->open()->latest('id')->first();

        if (! $ticket) {
            return [];
        }

        $command = $incoming->message_type === 'text' ? self::normalize((string) $incoming->message_text) : '';

        if (in_array($command, ['batal', 'batalkan', 'batal antrean', 'batal antrian', 'cancel'], true)
            && $ticket->status === ServiceTicket::WAITING) {
            $this->tickets->cancel($ticket);
            $this->state = self::freshState();
            $conversation->forceFill(['session_state' => $this->state, 'current_menu' => 'M-00'])->save();

            return [$this->reply(BotScript::queueCancelled($ticket->ticket_number)."\n\n".$this->mainMenuText(), ['LYN-BATAL', 'M-00'])];
        }

        $asked = (bool) preg_match('/^(cek\s+)?(antrean|antrian|posisi|status)(\s+(saya|antrean|antrian))?$/u', $command);
        $minutes = $asked ? 1 : max(1, (int) config('kancil.ticket.notice_minutes', 15));

        if ($ticket->last_notice_at && $ticket->last_notice_at->gt(now()->subMinutes($minutes))) {
            return [];
        }

        $ticket->update(['last_notice_at' => now()]);
        $handling = $ticket->status === ServiceTicket::HANDLING;

        return [$this->reply(
            BotScript::queueStatus($ticket->ticket_number, $ticket->queue_no, $this->tickets->position($ticket), $handling),
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
                $codes = ['AI-UNKNOWN', 'ESK-01'];
                $handoff = $this->handoffText($codes, $ticket);

                return [$this->reply(BotScript::msg('AI-UNKNOWN')."\n\n".$handoff, $codes, sender: 'ai')];
            }
        }

        return [$this->invalidInput($text)];
    }

    private function aiReply(LlmAnswer $answer): BotReply
    {
        $this->validInput();
        $path = BotScript::pathForFaq($answer->faqCodes[0]);
        $this->state['answer'] = $path ?? 'AI';
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

            return [$this->escalate(viaMenu3: false)];
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
