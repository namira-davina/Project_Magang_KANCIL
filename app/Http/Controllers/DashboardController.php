<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Statistik utama
        $totalPertanyaan = DB::table('messages')
            ->where('sender_type', 'user')
            ->count();

        $terjawabOtomatis = DB::table('messages')
            ->whereIn('sender_type', ['bot', 'ai'])
            ->count();

        $totalEskalasi = DB::table('escalation_logs')->count();

        $totalPengaduan = DB::table('complaints')->count();

        $pengaduanSelesai = DB::table('complaints')
            ->where('status', 'Selesai')
            ->count();

        $pengaduanDiproses = DB::table('complaints')
            ->where('status', 'Diproses')
            ->count();

        $pengaduanMenunggu = DB::table('complaints')
            ->where('status', 'Menunggu')
            ->count();


        // 2. Data grafik 7 hari terakhir
        $tanggalMulai = Carbon::today()->subDays(6);

        $dataGrafik = DB::table('messages')
            ->selectRaw('DATE(sent_at) as tanggal')
            ->selectRaw("
                SUM(CASE WHEN sender_type IN ('bot', 'ai')
                    THEN 1 ELSE 0 END) as jumlah_bot
            ")
            ->selectRaw("
                SUM(CASE WHEN sender_type = 'petugas'
                    THEN 1 ELSE 0 END) as jumlah_petugas
            ")
            ->where('sent_at', '>=', $tanggalMulai)
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $chartLabels = [];
        $chartBot = [];
        $chartPetugas = [];

        for ($i = 0; $i < 7; $i++) {
            $tanggal = $tanggalMulai->copy()->addDays($i);
            $key = $tanggal->format('Y-m-d');

            $chartLabels[] = $tanggal->translatedFormat('d M');

            $chartBot[] = (int) ($dataGrafik[$key]->jumlah_bot ?? 0);
            $chartPetugas[] = (int) ($dataGrafik[$key]->jumlah_petugas ?? 0);
        }


        // 3. Kategori FAQ paling sering digunakan
        $kategoriPopuler = DB::table('messages')
            ->join('faqs', 'messages.faq_id', '=', 'faqs.id')
            ->join('faq_categories', 'faqs.category_id', '=', 'faq_categories.id')
            ->where('messages.sender_type', 'user')
            ->select(
                'faq_categories.name',
                DB::raw('COUNT(messages.id) as jumlah')
            )
            ->groupBy('faq_categories.id', 'faq_categories.name')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        $totalKategori = $kategoriPopuler->sum('jumlah');

        foreach ($kategoriPopuler as $kategori) {
            $kategori->persentase = $totalKategori > 0
                ? round(($kategori->jumlah / $totalKategori) * 100)
                : 0;
        }


        // 4. Lima percakapan terbaru beserta pesan terakhir
        $percakapanTerbaru = DB::table('conversations')
            ->select('conversations.*')
            ->selectSub(function ($query) {
                $query->from('messages')
                    ->select('message_text')
                    ->whereColumn(
                        'messages.conversation_id',
                        'conversations.id'
                    )
                    ->orderByDesc('sent_at')
                    ->limit(1);
            }, 'pesan_terakhir')
            ->selectSub(function ($query) {
                $query->from('messages')
                    ->select('sender_type')
                    ->whereColumn(
                        'messages.conversation_id',
                        'conversations.id'
                    )
                    ->orderByDesc('sent_at')
                    ->limit(1);
            }, 'pengirim_terakhir')
            ->orderByDesc('conversations.last_message_at')
            ->limit(5)
            ->get();


        return view('dashboard.index', compact(
            'totalPertanyaan',
            'terjawabOtomatis',
            'totalEskalasi',
            'totalPengaduan',
            'pengaduanSelesai',
            'pengaduanDiproses',
            'pengaduanMenunggu',
            'chartLabels',
            'chartBot',
            'chartPetugas',
            'kategoriPopuler',
            'percakapanTerbaru'
        ));
    }
}
