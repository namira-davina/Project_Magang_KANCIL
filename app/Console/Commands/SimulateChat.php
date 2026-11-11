<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\Bot\BotEngine;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Console\Command;

/**
 * Uji bot tanpa WhatsApp: pesan dan balasan tersimpan sebagai percakapan kanal "simulasi".
 */
class SimulateChat extends Command
{
    protected $signature = 'kancil:simulasi
        {pesan? : Pesan yang dikirim (kosongkan untuk mode interaktif)}
        {--nomor=6280000000000 : Nomor WhatsApp simulasi}
        {--reset : Mulai sesi baru}';

    protected $description = 'Simulasikan chat WhatsApp ke bot (menu, pengaduan, AI) dari terminal';

    public function handle(BotEngine $engine, WhatsappMessenger $messenger): int
    {
        $conversation = Conversation::firstOrCreate(
            ['wa_chat_id' => 'simulasi:'.$this->option('nomor')],
            ['whatsapp_number' => $this->option('nomor'), 'user_name' => 'Simulasi', 'channel' => 'simulasi', 'started_at' => now()],
        );

        if ($this->option('reset')) {
            $conversation->update(['session_state' => null, 'last_inbound_at' => null]);
        }

        $text = $this->argument('pesan');

        if ($text !== null) {
            $this->send($conversation, $text, $engine, $messenger);

            return self::SUCCESS;
        }

        $this->info('Mode interaktif. Ketik "keluar" untuk berhenti.');

        while (($text = $this->ask('Anda')) !== null && $text !== 'keluar') {
            $this->send($conversation->fresh(), $text, $engine, $messenger);
        }

        return self::SUCCESS;
    }

    private function send(Conversation $conversation, string $text, BotEngine $engine, WhatsappMessenger $messenger): void
    {
        $previous = $conversation->last_inbound_at;
        $message = $conversation->messages()->create([
            'sender_type' => 'user',
            'message_type' => 'text',
            'message_text' => $text,
            'sent_at' => now(),
        ]);
        $conversation->update(['last_inbound_at' => now(), 'last_message_at' => now()]);

        foreach ($engine->handle($conversation, $message, $previous) as $reply) {
            $messenger->queue($conversation, $reply->text, $reply->sender, null, $reply->faqId, ['codes' => $reply->codes]);
            $this->line('');
            $this->line('<fg=green>Bot ['.implode(', ', $reply->codes).']</>');
            $this->line($reply->text);
            $this->line('');
        }
    }
}
