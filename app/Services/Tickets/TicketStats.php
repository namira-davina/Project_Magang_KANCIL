<?php

namespace App\Services\Tickets;

use App\Models\ServiceTicket;
use App\Services\Bot\BusinessHours;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka antrean petugas untuk halaman Antrean, Dashboard, dan Laporan bulanan.
 */
class TicketStats
{
    public function __construct(private readonly BusinessHours $hours) {}

    /** @return array{menunggu: int, ditangani: int, selesai_hari_ini: int, rata_tunggu_menit: int|null, tertua_menit: int|null} */
    public function today(): array
    {
        $byStatus = ServiceTicket::open()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $oldest = ServiceTicket::where('status', ServiceTicket::WAITING)->min('queued_at');
        $taken = ServiceTicket::whereNotNull('taken_at')->where('taken_at', '>=', now()->startOfDay())->get(['queued_at', 'taken_at']);

        return [
            'menunggu' => (int) ($byStatus[ServiceTicket::WAITING] ?? 0),
            'ditangani' => (int) ($byStatus[ServiceTicket::HANDLING] ?? 0),
            'selesai_hari_ini' => ServiceTicket::where('status', ServiceTicket::RESOLVED)->where('resolved_at', '>=', now()->startOfDay())->count(),
            'rata_tunggu_menit' => self::averageMinutes($taken, 'queued_at', 'taken_at'),
            'tertua_menit' => $oldest ? (int) Carbon::parse($oldest)->diffInMinutes(now()) : null,
        ];
    }

    /**
     * Rekap bulanan: tiket yang masuk antrean pada periode.
     * Tepat waktu = direspons/diambil petugas paling lambat 1 hari kerja setelah masuk antrean.
     */
    public function period(CarbonInterface $start, CarbonInterface $end): array
    {
        $tickets = ServiceTicket::whereBetween('queued_at', [$start, $end])
            ->get(['status', 'queued_at', 'taken_at', 'first_response_at', 'resolved_at']);

        $responded = $tickets->filter(fn (ServiceTicket $t) => ($t->first_response_at ?? $t->taken_at) !== null);
        $onTime = $responded->filter(fn (ServiceTicket $t) => $this->hours->businessDaysBetween($t->queued_at, $t->first_response_at ?? $t->taken_at) <= 1);
        $byStatus = $tickets->countBy('status');

        return [
            'total' => $tickets->count(),
            'selesai' => (int) ($byStatus[ServiceTicket::RESOLVED] ?? 0),
            'kedaluwarsa' => (int) ($byStatus[ServiceTicket::EXPIRED] ?? 0),
            'batal' => (int) ($byStatus[ServiceTicket::CANCELLED] ?? 0),
            'terbuka' => (int) (($byStatus[ServiceTicket::WAITING] ?? 0) + ($byStatus[ServiceTicket::HANDLING] ?? 0)),
            'direspons' => $responded->count(),
            'tepat_waktu' => $onTime->count(),
            'persen_tepat_waktu' => $responded->isNotEmpty() ? (int) round($onTime->count() / $responded->count() * 100) : null,
            'rata_tunggu_menit' => self::averageMinutes($tickets, 'queued_at', 'taken_at'),
            'rata_tangani_menit' => self::averageMinutes($tickets->where('status', ServiceTicket::RESOLVED), 'taken_at', 'resolved_at'),
        ];
    }

    /** Rata-rata selisih menit antara dua kolom (hanya baris yang keduanya terisi). */
    private static function averageMinutes(Collection $rows, string $from, string $to): ?int
    {
        $minutes = $rows->filter(fn ($r) => $r->{$from} && $r->{$to})
            ->map(fn ($r) => max(0, $r->{$from}->diffInMinutes($r->{$to})));

        return $minutes->isEmpty() ? null : (int) round($minutes->avg());
    }
}
