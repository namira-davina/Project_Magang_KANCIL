<?php

namespace App\Services\Bot;

use App\Models\Faq;
use Illuminate\Support\Collection;

/**
 * Isi jawaban menu Bogor diambil dari tabel FAQ (sumber tunggal, dikelola di dashboard), sesuai Inventarisasi 3.1.
 *
 * Gate status (Dokumen Skenario 3.2, Pesan 5): jawaban hanya tampil bila SEMUA FAQ sumbernya boleh ditampilkan.
 *  kancil.faq_scope = verified (default): terverifikasi + aktif
 *  kancil.faq_scope = all               : semua kecuali draf (cadangan darurat)
 * Bila ada sumber yang tertahan atau hilang, bot mengirim Pesan 5 (MSG-05) agar informasi yang belum pasti tidak tampil.
 */
class MenuAnswerResolver
{
    /** @return array{text: string, held: bool, faq_id: int|null, codes: list<string>} */
    public function resolve(string $path): array
    {
        $definition = BotScript::ANSWERS[$path] ?? throw new \InvalidArgumentException("Jawaban {$path} tidak dikenal.");
        $codes = $definition['faq'] ?? null;

        if ($codes === null) {
            return ['text' => BotScript::locationText(), 'held' => false, 'faq_id' => null, 'codes' => []];
        }

        $faqs = $this->allowed($codes);

        if ($faqs->count() !== count($codes)) {
            return ['text' => BotScript::msg('MSG-05'), 'held' => true, 'faq_id' => null, 'codes' => $codes];
        }

        $ordered = collect($codes)->map(fn (string $code) => $faqs[$code]);

        return [
            'text' => self::compose($ordered),
            'held' => false,
            'faq_id' => $ordered->first()->id,
            'codes' => $codes,
        ];
    }

    /** @param list<string> $codes */
    private function allowed(array $codes): Collection
    {
        $query = config('kancil.faq_scope') === 'all'
            ? Faq::where('verification_status', '!=', 'draft')
            : Faq::published();

        return $query->whereIn('code', $codes)->get()->keyBy('code');
    }

    /** Satu FAQ: jawabannya saja. Beberapa FAQ: tiap jawaban diberi judul pertanyaan (tebal). */
    public static function compose(Collection $faqs): string
    {
        if ($faqs->count() === 1) {
            return trim($faqs->first()->answer);
        }

        return $faqs
            ->map(fn (Faq $faq) => '*'.trim($faq->question).'*'."\n".trim($faq->answer))
            ->implode("\n\n");
    }
}
