<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\WebhookEvent;
use App\Services\ComplaintService;
use App\Services\Tickets\TicketQueueService;
use Illuminate\Console\Command;

/**
 * Tugas berkala: kembalikan percakapan ke bot setelah masa ambil alih habis,
 * tutup tiket antrean yang tidak terlayani, naikkan tiket pengaduan terlambat ke Pimpinan,
 * dan hapus log webhook lama.
 */
class RunMaintenance extends Command
{
    protected $signature = 'kancil:pemeliharaan';

    protected $description = 'Lepas ambil alih kedaluwarsa, tutup antrean basi, eskalasi tiket terlambat, bersihkan webhook lama';

    public function handle(ComplaintService $complaints, TicketQueueService $tickets): int
    {
        $released = 0;

        Conversation::whereIn('conversation_mode', [Conversation::MODE_HUMAN, Conversation::MODE_WAITING])
            ->whereNotNull('bot_paused_until')
            ->where('bot_paused_until', '<=', now())
            ->each(function (Conversation $conversation) use (&$released) {
                $conversation->returnToBot();
                $released++;
            });

        $expired = $tickets->expireStale();

        $escalated = $complaints->escalateOverdue();

        $deleted = WebhookEvent::where('created_at', '<', now()->subDays((int) config('balesotomatis.retention_days', 30)))->delete();

        $this->info("Dikembalikan ke bot: {$released}; antrean kedaluwarsa: {$expired}; tiket naik ke Pimpinan: {$escalated}; webhook dihapus: {$deleted}.");

        return self::SUCCESS;
    }
}
