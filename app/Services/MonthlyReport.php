<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\EscalationLog;
use App\Models\Message;
use App\Services\Bot\BotScript;
use App\Services\Bot\BusinessHours;
use App\Services\Tickets\TicketStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indikator laporan bulanan (Matriks Eskalasi 7.2 + KPI RAP).
 */
class MonthlyReport
{
    public const MONTHS = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public const SHORT_MONTHS = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function __construct(
        private readonly BusinessHours $hours,
        private readonly TicketStats $tickets,
    ) {}

    /** @return array<string, mixed> */
    public function build(int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $questions = Message::where('sender_type', 'user')->whereBetween('sent_at', [$start, $end])->count();
        $automatic = self::automaticAnswers()->whereBetween('sent_at', [$start, $end])->count();
        $escalations = EscalationLog::escalations()->whereBetween('escalated_at', [$start, $end])->count();
        $complaints = Complaint::whereBetween('submitted_at', [$start, $end])->get();

        return [
            'month' => $month,
            'year' => $year,
            'periodLabel' => self::MONTHS[$month].' '.$year,
            'totalPertanyaan' => $questions,
            'terjawabOtomatis' => $automatic,
            'totalEskalasi' => $escalations,
            'totalPengaduan' => $complaints->count(),
            'chart' => $this->yearlyChart($year),
            'topFaq' => $this->topFaq($start, $end),
            'eskalasiPerKode' => $this->escalationsByCode($start, $end),
            'tiket' => [
                'tepatWaktu' => $complaints->where('status', 'Selesai')->filter(fn (Complaint $c) => ! $this->resolvedLate($c))->count(),
                'selesai' => $complaints->where('status', 'Selesai')->count(),
                'level2' => $complaints->where('escalation_level', 2)->count(),
            ],
            'antrean' => $this->tickets->period($start, $end),
        ];
    }

    /** Jawaban otomatis = jawaban bot dari FAQ + semua jawaban AI. */
    public static function automaticAnswers()
    {
        return Message::query()->where(fn ($q) => $q
            ->where('sender_type', 'ai')
            ->orWhere(fn ($q) => $q->where('sender_type', 'bot')->whereNotNull('faq_id')));
    }

    private function yearlyChart(int $year): array
    {
        $rows = Message::where('sender_type', 'user')
            ->whereBetween('sent_at', [Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay()])
            ->get(['sent_at'])
            ->countBy(fn (Message $m) => (int) $m->sent_at->format('n'));

        return [
            'labels' => array_values(self::SHORT_MONTHS),
            'values' => array_map(fn ($m) => (int) ($rows[$m] ?? 0), range(1, 12)),
        ];
    }

    private function topFaq(Carbon $start, Carbon $end): Collection
    {
        return DB::table('messages')
            ->join('faqs', 'messages.faq_id', '=', 'faqs.id')
            ->leftJoin('faq_categories', 'faqs.category_id', '=', 'faq_categories.id')
            ->where('messages.sender_type', 'user')
            ->whereBetween('messages.sent_at', [$start, $end])
            ->select('faqs.id', 'faqs.code', 'faqs.question', 'faq_categories.name as category', DB::raw('COUNT(messages.id) as jumlah'))
            ->groupBy('faqs.id', 'faqs.code', 'faqs.question', 'faq_categories.name')
            ->orderByDesc('jumlah')->orderBy('faqs.id')
            ->limit(10)
            ->get();
    }

    private function escalationsByCode(Carbon $start, Carbon $end): Collection
    {
        $counts = EscalationLog::whereBetween('escalated_at', [$start, $end])
            ->whereNotNull('escalation_code')
            ->selectRaw('escalation_code, COUNT(*) as jumlah')
            ->groupBy('escalation_code')
            ->pluck('jumlah', 'escalation_code');

        $labels = [
            'A1' => 'Salah input 3x / di luar basis pengetahuan',
            'A2' => 'Minta petugas (menu 3 / 88)',
            'A3' => 'Jawaban ditahan (perlu verifikasi)',
            'A4' => '88 setelah jawaban belum lengkap',
            'B1' => 'Pengaduan (tiket dibuat)',
            'B0' => 'Pengaduan batal/terputus (bukan eskalasi)',
            'C1' => 'Kasus khusus: paspor hilang',
            'C2' => 'Kasus khusus: kondisi keluarga anak',
            'C3' => 'Kasus khusus: affidavit',
            'C4' => 'Kasus khusus: KANCIL PESAT',
            'C5' => 'Kasus khusus: pengiriman via pos',
            'C6' => 'Kasus khusus: WNA meninggal',
        ];

        return collect($labels)->map(fn ($label, $code) => (object) [
            'kode' => $code,
            'keterangan' => $label,
            'prioritas' => BotScript::PRIORITY[$code] ?? ($code === 'B1' ? 'Sesuai kategori' : '-'),
            'jumlah' => (int) ($counts[$code] ?? 0),
        ])->values();
    }

    private function resolvedLate(Complaint $c): bool
    {
        if (! $c->priority || ! $c->submitted_at || ! $c->resolved_at) {
            return false;
        }

        $limit = ComplaintService::DEADLINES[$c->priority][1];

        return $this->hours->businessDaysBetween($c->submitted_at, $c->resolved_at) > $limit;
    }
}
