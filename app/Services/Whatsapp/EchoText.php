<?php

namespace App\Services\Whatsapp;

/**
 * Normalisasi teks untuk mencocokkan echo `outgoing_chat` dengan pesan yang kita kirim.
 *
 * Gateway/WhatsApp terbukti (prod 20 Nov 2026) memadatkan spasi ganda dan membuang garis pemisah (----),
 * sehingga echo jawaban bot tidak cocok persis dan dianggap balasan petugas dari HP (bot berhenti).
 * Pembandingan dibuat tahan terhadap perbedaan kosmetik: whitespace, garis pemisah, CRLF, variation selector
 * emoji, karakter lebar-nol, kutip lengkung, dan penanda format WhatsApp (*, _, ~, `).
 */
final class EchoText
{
    public static function normalize(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^\s*[-–—_=]{3,}\s*$/mu', '', $text) ?? $text;
        // Variation selector (FE0E/FE0F), zero-width (200B-200D, 2060, FEFF), soft hyphen.
        $text = preg_replace('/[\x{FE0E}\x{FE0F}\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00AD}]/u', '', $text) ?? $text;
        $text = strtr($text, ['“' => '"', '”' => '"', '„' => '"', '‘' => "'", '’' => "'", '‚' => "'"]);
        $text = preg_replace('/[*_~`]/u', '', $text) ?? $text;

        // Semua whitespace (spasi ganda, NBSP, baris kosong, spasi awal baris) dianggap satu spasi.
        return trim(preg_replace('/[\s\x{00A0}\x{202F}\x{2007}]+/u', ' ', $text) ?? $text);
    }

    public static function same(?string $a, ?string $b): bool
    {
        $a = self::normalize($a);

        return $a !== '' && $a === self::normalize($b);
    }
}
