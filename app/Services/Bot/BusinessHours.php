<?php

namespace App\Services\Bot;

use App\Models\BotSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Jam layanan petugas dari bot_settings (working_days, working_hours_start/end), zona WIB.
 */
class BusinessHours
{
    public const TIMEZONE = 'Asia/Jakarta';

    private const DAYS = [
        'senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4,
        'jumat' => 5, 'sabtu' => 6, 'minggu' => 7,
    ];

    private ?array $settings = null;

    public function isOpen(?CarbonInterface $at = null): bool
    {
        $at = Carbon::instance($at ?? now())->setTimezone(self::TIMEZONE);

        if (! in_array($at->dayOfWeekIso, $this->workingDays(), true)) {
            return false;
        }

        $time = $at->format('H:i');

        return $time >= $this->setting('working_hours_start', '08:00')
            && $time < $this->setting('working_hours_end', '16:00');
    }

    public function isBusinessDay(CarbonInterface $day): bool
    {
        return in_array(Carbon::instance($day)->setTimezone(self::TIMEZONE)->dayOfWeekIso, $this->workingDays(), true);
    }

    /** Jumlah hari kerja yang sudah lewat setelah tanggal $from sampai tanggal $to. */
    public function businessDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $cursor = Carbon::instance($from)->setTimezone(self::TIMEZONE)->startOfDay()->addDay();
        $end = Carbon::instance($to)->setTimezone(self::TIMEZONE)->startOfDay();
        $days = 0;

        while ($cursor->lte($end)) {
            if ($this->isBusinessDay($cursor)) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }

    /** Teks jam layanan untuk pesan bot; null bila belum dikonfirmasi (bot_settings.service_hours_text). */
    public function publicText(): ?string
    {
        $text = trim($this->setting('service_hours_text', ''));

        return $text === '' ? null : $text;
    }

    /** @return list<int> nomor hari ISO (1 = Senin) */
    public function workingDays(): array
    {
        $raw = strtolower(str_replace(['–', '—', ' ', "'"], ['-', '-', '', ''], $this->setting('working_days', 'Senin-Jumat')));

        if (str_contains($raw, '-')) {
            [$from, $to] = explode('-', $raw, 2);
            $start = self::DAYS[$from] ?? 1;
            $end = self::DAYS[$to] ?? 5;

            return range($start, max($start, $end));
        }

        $days = collect(explode(',', $raw))->map(fn ($d) => self::DAYS[$d] ?? null)->filter()->values()->all();

        return $days ?: [1, 2, 3, 4, 5];
    }

    private function setting(string $key, string $default): string
    {
        $this->settings ??= BotSetting::pluck('setting_value', 'setting_key')->all();

        return (string) ($this->settings[$key] ?? $default);
    }
}
