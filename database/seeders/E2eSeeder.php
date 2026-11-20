<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\ServiceTicket;
use App\Services\Bot\BotScript;
use App\Services\Tickets\TicketQueueService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data uji E2E dashboard (Playwright, tests/e2e). Hanya dipakai lewat
 * `php artisan migrate:fresh --seed --seeder=Database\\Seeders\\E2eSeeder` pada database SQLite khusus E2E.
 * Tidak pernah dijalankan oleh DatabaseSeeder biasa.
 */
class E2eSeeder extends Seeder
{
    private int $seq = 0;

    public function run(TicketQueueService $tickets): void
    {
        abort_if(app()->isProduction(), 500, 'E2eSeeder tidak boleh dijalankan di production.');

        $this->call(DatabaseSeeder::class);

        // Seperti prod 20 Nov: 61 FAQ inventaris terverifikasi; dari FAQ baru, admin sudah memverifikasi PSP-014 (Rusak),
        // PSP-013/015/016 masih perlu verifikasi (jawaban menu-nya = Pesan 5).
        Faq::whereNotIn('code', ['PSP-013', 'PSP-015', 'PSP-016'])->update(['verification_status' => 'terverifikasi', 'is_active' => true]);

        // Antrean: 1 menunggu (untuk Ambil + Jadikan Pengaduan), 1 dari kemarin yang diambil hari ini (tidak boleh
        // menggelembungkan rata-rata), 1 hari ini yang diambil setelah 2 menit.
        $waiting = $this->ticket($tickets, 'Pemohon E2E Antre', now()->subMinute(), 'Petugas customer care kurang ramah saat memberi informasi');
        $this->ticket($tickets, 'Pemohon E2E Kemarin', now()->subDay()->setTime(15, 0), 'Pertanyaan kemarin sore')
            ->update(['status' => ServiceTicket::HANDLING, 'taken_at' => now()->subSeconds(30)]);
        $this->ticket($tickets, 'Pemohon E2E Cepat', now()->subMinutes(3), 'Pertanyaan cepat')
            ->update(['status' => ServiceTicket::HANDLING, 'taken_at' => now()->subMinute()]);

        // 13 pengaduan (lebih dari 1 halaman = 10) dengan 4 kategori dan 3 status.
        $categories = collect(BotScript::COMPLAINT_CATEGORIES)
            ->map(fn ($c) => ComplaintCategory::where('name', $c[0])->value('id'))->values();
        $statuses = ['Menunggu', 'Diproses', 'Selesai'];

        for ($i = 1; $i <= 13; $i++) {
            Complaint::create([
                'ticket_number' => sprintf('ADU-%s-%04d', now()->format('Ym'), $i),
                'category_id' => $categories[($i - 1) % 4],
                'user_name' => "Pelapor E2E {$i}",
                'whatsapp_number' => '62812000099'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'complaint_text' => "Isi pengaduan uji nomor {$i}.",
                'status' => $statuses[($i - 1) % 3],
                'priority' => BotScript::COMPLAINT_CATEGORIES[(string) ((($i - 1) % 4) + 1)][1],
                'escalation_level' => 1,
                'submitted_at' => now()->subMinutes(100 - $i),
            ]);
        }

        $this->command?->info("E2E siap: tiket menunggu {$waiting->ticket_number}, 13 pengaduan, admin/password.");
    }

    private function ticket(TicketQueueService $tickets, string $name, Carbon $at, string $question): ServiceTicket
    {
        $n = ++$this->seq;
        $chat = Conversation::create([
            'wa_chat_id' => "62812000088{$n}@s.whatsapp.net",
            'whatsapp_number' => "62812000088{$n}",
            'user_name' => $name,
            'channel' => 'whatsapp',
            'conversation_mode' => Conversation::MODE_WAITING,
            'started_at' => $at,
            'last_message_at' => $at,
        ]);

        Carbon::setTestNow($at);
        [$ticket] = $tickets->open($chat, null, $question);
        Carbon::setTestNow();

        return $ticket->fresh();
    }
}
