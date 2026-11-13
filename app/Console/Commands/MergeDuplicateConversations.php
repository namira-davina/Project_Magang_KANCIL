<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\ServiceTicket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gabungkan percakapan ganda untuk nomor WhatsApp yang sama.
 *
 * Penyebab lama: pesan masuk memakai chat_id @lid, sedangkan echo pesan keluar memakai
 *
 * @s.whatsapp.net, sehingga echo membuat percakapan baru dan tercatat sebagai "Petugas".
 * Perintah ini memindahkan pesan, eskalasi, pengaduan, dan log AI ke percakapan tertua,
 * membuang echo balasan bot yang terduplikasi, lalu menghapus percakapan sisa.
 */
class MergeDuplicateConversations extends Command
{
    protected $signature = 'kancil:gabung-percakapan {--dry-run : Tampilkan saja tanpa mengubah data}';

    protected $description = 'Gabungkan percakapan ganda dengan nomor WhatsApp yang sama';

    public function handle(): int
    {
        $phones = Conversation::whereNotNull('whatsapp_number')
            ->select('whatsapp_number')
            ->groupBy('whatsapp_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('whatsapp_number');

        if ($phones->isEmpty()) {
            $this->info('Tidak ada percakapan ganda.');

            return self::SUCCESS;
        }

        foreach ($phones as $phone) {
            $conversations = Conversation::where('whatsapp_number', $phone)->orderBy('id')->get();
            $target = $conversations->first();
            $sources = $conversations->slice(1);

            $this->line("{$phone}: gabung #".$sources->pluck('id')->implode(', #')." ke #{$target->id}");

            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($target, $sources) {
                foreach ($sources as $source) {
                    $this->mergeInto($target, $source);
                }

                $this->dropBotEchoes($target);
                $this->refresh($target);
            });
        }

        $this->info($this->option('dry-run') ? 'Dry run selesai, tidak ada data yang diubah.' : 'Selesai.');

        return self::SUCCESS;
    }

    private function mergeInto(Conversation $target, Conversation $source): void
    {
        foreach (['messages', 'escalation_logs', 'complaints', 'llm_logs', 'service_tickets'] as $table) {
            DB::table($table)->where('conversation_id', $source->id)->update(['conversation_id' => $target->id]);
        }

        // Satu percakapan hanya boleh punya satu tiket antrean terbuka: pertahankan yang paling awal masuk.
        $open = $target->serviceTickets()->open()->fifo()->get();
        $open->skip(1)->each(fn (ServiceTicket $ticket) => $ticket->update([
            'status' => ServiceTicket::CANCELLED,
            'resolved_at' => now(),
            'close_reason' => 'Digabung dengan tiket '.$open->first()->ticket_number.'.',
        ]));

        $target->fill([
            'user_name' => $target->user_name ?: $source->user_name,
            'wa_chat_id' => $target->wa_chat_id ?: $source->wa_chat_id,
            'device_key' => $target->device_key ?: $source->device_key,
            'session_state' => $target->session_state ?: $source->session_state,
        ]);

        // Hapus dulu sumbernya: wa_chat_id unik.
        $source->delete();
        $target->save();
    }

    /** Echo balasan bot/AI yang dulu tercatat sebagai "Petugas" (bukan dari dashboard). */
    private function dropBotEchoes(Conversation $target): void
    {
        $candidates = $target->messages()
            ->where('sender_type', 'petugas')
            ->whereNull('sender_user_id')
            ->whereNull('client_uuid')
            ->get();

        foreach ($candidates as $echo) {
            $original = $target->messages()
                ->whereIn('sender_type', ['bot', 'ai', 'petugas', 'system'])
                ->whereKeyNot($echo->id)
                ->whereNotNull('client_uuid')
                ->where('message_text', $echo->message_text)
                ->whereBetween('sent_at', [$echo->sent_at->copy()->subMinutes(10), $echo->sent_at->copy()->addMinutes(10)])
                ->first();

            if (! $original) {
                continue;
            }

            if (! $original->external_message_id && $echo->external_message_id) {
                $original->external_message_id = $echo->external_message_id;
                $echo->external_message_id = null;
                $echo->save();
                $original->save();
            }

            $echo->delete();
        }
    }

    private function refresh(Conversation $target): void
    {
        $last = $target->messages()->max('sent_at');
        $lastInbound = $target->messages()->where('sender_type', 'user')->max('sent_at');
        $realOfficerReply = $target->messages()->where('sender_type', 'petugas')->exists();

        $target->last_message_at = $last ?? $target->last_message_at;
        $target->last_inbound_at = $lastInbound ?? $target->last_inbound_at;

        // Mode "Sedang Ditangani" yang hanya dipicu echo bot dikembalikan ke bot.
        if ($target->conversation_mode === Conversation::MODE_HUMAN && ! $realOfficerReply && ! $target->assigned_to) {
            $target->conversation_mode = Conversation::MODE_BOT;
            $target->bot_paused_until = null;
        }

        // Tiket antrean yang masih menunggu: pemohon tetap di antrean.
        if ($target->conversation_mode === Conversation::MODE_BOT
            && $target->serviceTickets()->where('status', ServiceTicket::WAITING)->exists()) {
            $target->conversation_mode = Conversation::MODE_WAITING;
            $target->bot_paused_until = null;
        }

        $target->save();
    }
}
