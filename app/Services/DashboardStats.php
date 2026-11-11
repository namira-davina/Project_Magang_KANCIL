<?php

namespace App\Services;

use App\Models\EscalationLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardStats
{
    /** @return array<string, mixed> */
    public function all(): array
    {
        return [
            ...$this->messageTotals(),
            ...$this->complaintTotals(),
            'totalEskalasi' => EscalationLog::escalations()->count(),
            ...$this->chart(),
            'kategoriPopuler' => $this->popularCategories(),
            'percakapanTerbaru' => $this->latestConversations(),
        ];
    }

    private function messageTotals(): array
    {
        // Terjawab otomatis = jawaban dari FAQ (bot) + jawaban AI; pesan menu tidak dihitung.
        $row = DB::table('messages')
            ->selectRaw("SUM(CASE WHEN sender_type = 'user' THEN 1 ELSE 0 END) as pertanyaan")
            ->selectRaw("SUM(CASE WHEN sender_type = 'ai' OR (sender_type = 'bot' AND faq_id IS NOT NULL) THEN 1 ELSE 0 END) as otomatis")
            ->first();

        return [
            'totalPertanyaan' => (int) $row->pertanyaan,
            'terjawabOtomatis' => (int) $row->otomatis,
        ];
    }

    private function complaintTotals(): array
    {
        $byStatus = DB::table('complaints')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'totalPengaduan' => (int) $byStatus->sum(),
            'pengaduanSelesai' => (int) ($byStatus['Selesai'] ?? 0),
            'pengaduanDiproses' => (int) ($byStatus['Diproses'] ?? 0),
            'pengaduanMenunggu' => (int) ($byStatus['Menunggu'] ?? 0),
        ];
    }

    /** Messages per day for the last 7 days (bot/ai vs petugas). */
    private function chart(): array
    {
        $start = Carbon::today()->subDays(6);

        $rows = DB::table('messages')
            ->selectRaw('DATE(sent_at) as tanggal')
            ->selectRaw("SUM(CASE WHEN sender_type IN ('bot', 'ai') THEN 1 ELSE 0 END) as jumlah_bot")
            ->selectRaw("SUM(CASE WHEN sender_type = 'petugas' THEN 1 ELSE 0 END) as jumlah_petugas")
            ->where('sent_at', '>=', $start)
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $labels = $bot = $petugas = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');

            $labels[] = $day->translatedFormat('d M');
            $bot[] = (int) ($rows[$key]->jumlah_bot ?? 0);
            $petugas[] = (int) ($rows[$key]->jumlah_petugas ?? 0);
        }

        return [
            'chartLabels' => $labels,
            'chartBot' => $bot,
            'chartPetugas' => $petugas,
        ];
    }

    private function popularCategories(): Collection
    {
        $categories = DB::table('messages')
            ->join('faqs', 'messages.faq_id', '=', 'faqs.id')
            ->join('faq_categories', 'faqs.category_id', '=', 'faq_categories.id')
            ->where('messages.sender_type', 'user')
            ->select('faq_categories.name', DB::raw('COUNT(messages.id) as jumlah'))
            ->groupBy('faq_categories.id', 'faq_categories.name')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        $total = $categories->sum('jumlah');

        foreach ($categories as $category) {
            $category->persentase = $total > 0 ? round(($category->jumlah / $total) * 100) : 0;
        }

        return $categories;
    }

    /** Five newest conversations with their last message and its sender. */
    private function latestConversations(): Collection
    {
        $lastMessage = fn (string $column) => function ($query) use ($column) {
            $query->from('messages')
                ->select($column)
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->orderByDesc('sent_at')
                ->limit(1);
        };

        return DB::table('conversations')
            ->select('conversations.*')
            ->selectSub($lastMessage('message_text'), 'pesan_terakhir')
            ->selectSub($lastMessage('sender_type'), 'pengirim_terakhir')
            ->orderByDesc('conversations.last_message_at')
            ->limit(5)
            ->get();
    }
}
