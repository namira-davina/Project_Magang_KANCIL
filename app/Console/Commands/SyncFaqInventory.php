<?php

namespace App\Console\Commands;

use App\Models\Faq;
use App\Services\FaqInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Selaraskan FAQ di database dengan data resmi (database/data/faqs.php = Inventarisasi 3.1 + PSP-013..015).
 *
 * Default hanya menampilkan perbedaan (dry-run). --apply membuat FAQ yang belum ada dan memperbarui isi
 * (pertanyaan/jawaban/jalur) yang berbeda; FAQ yang pernah disunting petugas di dashboard (updated_by terisi)
 * dilewati kecuali --force. Status verifikasi TIDAK diubah kecuali --reset-status.
 */
class SyncFaqInventory extends Command
{
    protected $signature = 'kancil:faq-sync
        {--apply : Terapkan perubahan (tanpa ini hanya menampilkan perbedaan)}
        {--force : Timpa juga FAQ yang pernah disunting di dashboard}
        {--reset-status : Samakan status verifikasi/aktif dengan data (mis. kembali ke perlu_verifikasi)}';

    protected $description = 'Bandingkan/selaraskan FAQ di database dengan Inventarisasi FAQ 3.1';

    private const FIELDS = ['question', 'answer', 'menu_path'];

    public function handle(FaqInventory $inventory): int
    {
        $apply = (bool) $this->option('apply');
        $summary = ['baru' => 0, 'isi' => 0, 'status' => 0, 'status_beda' => 0, 'dilewati' => 0, 'sama' => 0];

        DB::transaction(function () use ($inventory, $apply, &$summary) {
            foreach ($inventory->rows() as $code => $row) {
                $faq = Faq::where('code', $code)->first();

                if (! $faq) {
                    $summary['baru']++;
                    $this->line("<info>+ {$code}</info> belum ada".($apply ? ' → dibuat ('.FaqInventory::status($row).')' : ''));

                    if ($apply) {
                        $inventory->create($row);
                    }

                    continue;
                }

                $changes = $this->contentChanges($faq, $row);
                $statusChange = $this->statusChange($faq, $row);

                if ($changes === [] && $statusChange === null) {
                    $summary['sama']++;

                    continue;
                }

                if ($changes !== []) {
                    $edited = $faq->updated_by !== null && ! $this->option('force');
                    $summary[$edited ? 'dilewati' : 'isi']++;
                    $this->line(($edited ? '<comment>! ' : '<info>~ ')."{$code}</> ".implode(', ', array_keys($changes))
                        .($edited ? ' berbeda, tetapi pernah disunting di dashboard (pakai --force untuk menimpa)' : ' berbeda'));

                    foreach ($changes as $field => [$old, $new]) {
                        $this->line("    {$field}: ".Str::limit(self::flat($old), 90).' → '.Str::limit(self::flat($new), 90));
                    }

                    if ($apply && ! $edited) {
                        $faq->update(array_map(fn ($pair) => $pair[1], $changes));
                    }
                }

                if ($statusChange !== null) {
                    $reset = (bool) $this->option('reset-status');
                    $summary[$reset ? 'status' : 'status_beda']++;
                    $this->line("  <comment>status</comment> {$code}: {$statusChange[0]} → {$statusChange[1]}".($reset ? '' : ' (tidak diubah; pakai --reset-status)'));

                    if ($apply && $reset) {
                        $faq->update(['verification_status' => $statusChange[1], 'is_active' => $statusChange[1] === 'terverifikasi', 'verified_at' => null, 'verified_by' => null]);
                    }
                }
            }
        });

        $this->newLine();
        $this->info(($apply ? 'Diterapkan' : 'Dry-run (tidak ada perubahan)').": baru {$summary['baru']}, isi {$summary['isi']}, status {$summary['status']}, status beda (dibiarkan) {$summary['status_beda']}, dilewati {$summary['dilewati']}, sama {$summary['sama']}.");

        if ($apply) {
            Log::info('kancil:faq-sync diterapkan.', $summary + ['force' => (bool) $this->option('force'), 'reset_status' => (bool) $this->option('reset-status')]);
        }

        return self::SUCCESS;
    }

    /** @return array<string, array{0: string|null, 1: string}> */
    private function contentChanges(Faq $faq, array $row): array
    {
        $changes = [];

        foreach (self::FIELDS as $field) {
            if (trim((string) $faq->{$field}) !== trim((string) $row[$field])) {
                $changes[$field] = [$faq->{$field}, $row[$field]];
            }
        }

        return $changes;
    }

    /** @return array{0: string, 1: string}|null */
    private function statusChange(Faq $faq, array $row): ?array
    {
        $target = FaqInventory::status($row);
        $current = $faq->verification_status;

        return $current !== $target || $faq->is_active !== ($target === 'terverifikasi') ? [$current.($faq->is_active ? '+aktif' : ''), $target] : null;
    }

    private static function flat(?string $text): string
    {
        return preg_replace('/\s+/u', ' ', (string) $text);
    }
}
