<?php

namespace Tests\Unit;

use App\Services\Whatsapp\EchoText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EchoTextTest extends TestCase
{
    public function test_prod_echo_pair_from_audit_matches(): void
    {
        $pair = json_decode(file_get_contents(__DIR__.'/../Fixtures/echo-prod-2026-11-20.json'), true);

        $this->assertNotSame($pair['bot'], $pair['echo']);
        $this->assertTrue(EchoText::same($pair['echo'], $pair['bot']));
    }

    /** @return array<string, array{string, string}> */
    public static function cosmeticVariants(): array
    {
        $bot = "*Judul:*\n\n* 5 Tahun  : Rp 650.000\n✅ e-KTP\n\n------------------------\n\n_Ketik 0_ untuk “kembali”";

        return [
            'spasi ganda dipadatkan' => [$bot, str_replace('  ', ' ', $bot)],
            'garis pemisah dibuang' => [$bot, str_replace("------------------------\n\n", '', $bot)],
            'CRLF' => [$bot, str_replace("\n", "\r\n", $bot)],
            'emoji + variation selector' => [$bot, str_replace('✅', "✅\u{FE0F}", $bot)],
            'kutip lengkung jadi lurus' => [$bot, str_replace(['“', '”'], '"', $bot)],
            'penanda format dibuang' => [$bot, str_replace(['*', '_'], '', $bot)],
            'NBSP' => [$bot, str_replace(' : ', "\u{00A0}: ", $bot)],
            'zero-width space' => [$bot, str_replace('e-KTP', "e-\u{200B}KTP", $bot)],
            'trailing whitespace' => [$bot, $bot."  \n"],
            'garis em dash' => [str_replace('------------------------', '———', $bot), $bot],
        ];
    }

    #[DataProvider('cosmeticVariants')]
    public function test_cosmetic_differences_are_ignored(string $sent, string $echo): void
    {
        $this->assertTrue(EchoText::same($echo, $sent));
    }

    public function test_different_content_does_not_match(): void
    {
        $this->assertFalse(EchoText::same('Paspor 5 Tahun Rp 650.000', 'Paspor 5 Tahun Rp 950.000'));
        $this->assertFalse(EchoText::same('Baik Pak, saya bantu.', 'Selamat siang ka, ada yang bisa kami bantu?'));
    }

    public function test_empty_text_never_matches(): void
    {
        $this->assertFalse(EchoText::same('', ''));
        $this->assertFalse(EchoText::same(null, null));
        $this->assertFalse(EchoText::same("---\n\n", '  '));
    }
}
