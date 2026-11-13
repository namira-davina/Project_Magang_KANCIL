<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\WebhookEvent;
use App\Services\ComplaintService;
use Illuminate\Console\Command;

/**
 * Tugas berkala: kembalikan percakapan ke bot setelah masa ambil alih habis,
 * naikkan tiket terlambat ke Pimpinan, dan hapus log webhook lama.
 */
class RunMaintenance extends Command
{
    protected $signature = 'kancil:pemeliharaan';

    protected $description = 'Lepas ambil alih yang kedaluwarsa, eskalasi tiket terlambat, bersihkan webhook lama';

    public function handle(ComplaintService $complaints): int
    {
        $released = Conversation::whereIn('conversation_mode', [Conversation::MODE_HUMAN, Conversation::MODE_WAITING])
            ->whereNotNull('bot_paused_until')
            ->where('bot_paused_until', '<=', now())
            ->update(['conversation_mode' => Conversation::MODE_BOT, 'bot_paused_until' => null]);

        $escalated = $complaints->escalateOverdue();

        $deleted = WebhookEvent::where('created_at', '<', now()->subDays((int) config('balesotomatis.retention_days', 30)))->delete();

        $this->info("Dikembalikan ke bot: {$released}; tiket naik ke Pimpinan: {$escalated}; webhook dihapus: {$deleted}.");

        return self::SUCCESS;
    }
}
