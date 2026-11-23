<?php

namespace Tests\Feature;

use App\Models\EscalationLog;
use App\Models\ServiceTicket;
use App\Services\Bot\BotEngine;
use App\Services\Bot\BotScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Feedback Imigrasi 23 Nov 2026 (feedback user 4): "menu 2 ... ada yang bug untuk kembali ke menu utamanya".
 * Footer jawaban gaya Bogor menulis "Ketik 0 untuk kembali ke menu sebelumnya", padahal 0 membawa ke menu utama; di menu 2
 * (bertingkat 3) pemohon harus mengulang 2 > 1 setiap selesai membaca satu tarif (terlihat di log prod 23 Nov).
 * Keputusan: setelah jawaban 9 = menu sebelumnya (submenu tempat jawaban dipilih), 0 = menu utama, 1 = Petugas.
 */
class AnswerNavigationTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->atOfficeHours();
        $this->configureIntegration('laravel');
        $this->seedFaqs();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Rute angka dari menu utama ke setiap jawaban, diturunkan dari BotScript::MENUS (jawaban baru otomatis ikut teruji).
     *
     * @return array<string, array{string, list<string>, string}> jalur jawaban => [jalur, rute input, menu induk]
     */
    public static function answerRoutes(): array
    {
        $routes = [];
        $walk = function (string $menu, array $inputs) use (&$walk, &$routes): void {
            foreach (BotScript::MENUS[$menu]['options'] as $key => [, $target]) {
                if (str_starts_with($target, 'M-')) {
                    $walk($target, [...$inputs, (string) $key]);
                } elseif (str_starts_with($target, 'ANS:')) {
                    $path = substr($target, 4);
                    $routes[$path] = [$path, [...$inputs, (string) $key], $menu];
                }
            }
        };
        $walk('M-00', []);

        return $routes;
    }

    public function test_every_answer_path_is_reachable_from_main_menu(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(BotScript::ANSWERS), array_keys(self::answerRoutes()));
    }

    #[DataProvider('answerRoutes')]
    public function test_nine_after_answer_returns_to_the_submenu_where_it_was_chosen(string $path, array $route, string $parent): void
    {
        $this->reply('Halo');
        $answer = $this->conversation($route);
        $this->assertSame('ANS-'.$path, $answer->codes[0]);
        $this->assertStringEndsWith(BotScript::answerFooter(BotScript::isForeignerMenu($parent)), $answer->text);

        $back = $this->reply('9');

        $this->assertSame([$parent], $back->codes);
        $this->assertSame(BotScript::menu($parent), $back->text, 'Teks submenu persis sama dengan saat pertama dibuka.');
        $this->assertSame($parent, $this->chat->fresh()->current_menu);
        $this->assertNull($this->chat->fresh()->session_state['answer']);
        $this->assertSame(0, EscalationLog::count(), '9 bukan permintaan petugas.');
        $this->assertSame(0, ServiceTicket::count());

        // Dari submenu itu, jawaban yang sama bisa dibuka lagi tanpa lewat menu utama.
        $this->assertSame('ANS-'.$path, $this->reply(end($route))->codes[0]);
    }

    #[DataProvider('answerRoutes')]
    public function test_zero_99_and_menu_after_answer_go_to_main_menu(string $path, array $route): void
    {
        foreach (['0', '99', 'menu', 'MENU'] as $command) {
            $this->reply('Halo');
            $this->conversation($route);

            $main = $this->reply($command);

            $this->assertSame(['M-00'], $main->codes, "{$path} lalu {$command}");
            $this->assertStringContainsString('Silakan pilih menu di bawah ini:', $main->text);
        }
    }

    #[DataProvider('answerRoutes')]
    public function test_one_after_answer_reaches_officer_with_answer_path(string $path, array $route): void
    {
        $this->reply('Halo');
        $this->conversation($route);

        $handoff = $this->reply('1');

        $this->assertSame('ESK-01', $handoff->codes[0]);
        $this->assertHandoff($handoff->text);
        $this->assertSame($path, EscalationLog::sole()->menu_path);
        $this->assertSame(BotScript::ANSWERS[$path]['code'] ?? 'A2', EscalationLog::sole()->escalation_code);
    }

    public function test_answer_footer_lists_nine_zero_one_in_order_without_double_spaces(): void
    {
        $footer = BotScript::answerFooter();

        $this->assertSame(
            "------------------------\n\nButuh bantuan lebih lanjut?\n\n*Ketik 9* untuk kembali ke menu sebelumnya\n"
            ."*Ketik 0* untuk kembali ke menu utama\n*Ketik 1* untuk menghubungi Petugas\n\n*Terima kasih.*",
            $footer,
        );
        $this->assertStringNotContainsString('  ', $footer, 'Gateway memadatkan spasi ganda pada echo (jebakan prod).');
        $this->assertStringNotContainsString('*Ketik 0* untuk kembali ke menu sebelumnya', $footer, 'Label lama yang menyesatkan.');
    }

    public function test_foreigner_answer_footer_has_english_in_brackets(): void
    {
        // Permintaan pemilik 23 Nov: footer jawaban menu WNA diberi versi Inggris di dalam kurung.
        $footer = BotScript::answerFooter(english: true);

        $this->assertSame(
            "------------------------\n\nButuh bantuan lebih lanjut? _(Need further assistance?)_\n\n"
            ."*Ketik 9* untuk kembali ke menu sebelumnya _(Type 9 to return to the previous menu)_\n"
            ."*Ketik 0* untuk kembali ke menu utama _(Type 0 to return to the main menu)_\n"
            ."*Ketik 1* untuk menghubungi Petugas _(Type 1 to contact an officer)_\n\n"
            .'*Terima kasih.* _(Thank you.)_',
            $footer,
        );
        $this->assertStringNotContainsString('  ', $footer);
    }

    public function test_only_foreigner_menus_are_bilingual(): void
    {
        foreach (array_keys(BotScript::MENUS) as $code) {
            $this->assertSame(in_array($code, ['M-08', 'M-09', 'M-10'], true), BotScript::isForeignerMenu($code), $code);
        }
        $this->assertFalse(BotScript::isForeignerMenu(null));
        $this->assertFalse(BotScript::isForeignerMenu('M-99'));
    }

    #[DataProvider('answerRoutes')]
    public function test_footer_language_follows_menu_for_every_answer(string $path, array $route, string $parent): void
    {
        $this->reply('Halo');
        $text = $this->conversation($route)->text;
        $foreigner = str_starts_with($path, '1>');

        $this->assertStringEndsWith(BotScript::answerFooter($foreigner), $text);
        $this->assertSame($foreigner, str_contains($text, '(Type 9 to return to the previous menu)'), "{$path}: footer Inggris hanya di menu WNA.");

        // Salah input setelah jawaban mengulang footer dengan bahasa yang sama.
        $this->assertStringEndsWith(BotScript::answerFooter($foreigner), $this->reply('5')->text);
    }

    public function test_held_foreigner_answer_and_passport_answer_footers(): void
    {
        $this->holdFaq('TRF-005', 'PSP-001');
        $this->reply('Halo');

        $this->assertStringEndsWith(BotScript::answerFooter(english: true), $this->conversation(['2', '1', '1'])->text);
        $this->assertStringEndsWith(BotScript::answerFooter(), $this->conversation(['0', '1', '2'])->text, 'Menu Paspor WNI tetap bahasa Indonesia.');
    }

    public function test_tester_session_from_prod_log_reads_all_foreigner_tariffs_without_main_menu(): void
    {
        // Log prod 23 Nov 09.33-09.46: 2,1,1,0,2,1,2,0,2,1,3,0,... Kini cukup 9 di antara tarif.
        $this->reply('Halo');
        $this->conversation(['2', '1']);

        foreach (range(1, 6) as $option) {
            $this->assertSame("ANS-1>1>{$option}", $this->reply((string) $option)->codes[0]);
            $this->assertSame(['M-09'], $this->reply('9')->codes);
        }

        $this->assertSame(['M-08'], $this->reply('7')->codes, '7 di submenu tarif = kembali ke menu Izin Tinggal.');
        $this->assertSame(['M-10'], $this->reply('2')->codes);
        $this->assertSame('ANS-1>2>1', $this->reply('1')->codes[0]);
        $this->assertSame(['M-10'], $this->reply('9')->codes);
        $this->assertSame('ANS-1>2>3', $this->reply('3')->codes[0]);
        $this->assertSame(['M-00'], $this->reply('0')->codes);
    }

    public function test_passport_answers_return_to_passport_list(): void
    {
        // Log prod 23 Nov (pola 1,N,0,1,...): menu 1 ikut diperbaiki.
        $this->reply('Halo');
        $this->conversation(['1', '2']);

        $back = $this->reply('9');
        $this->assertSame(['M-01'], $back->codes);
        $this->assertStringContainsString("Ketik *11* Cek Status Paspor\nKetik *12* untuk kembali ke menu sebelumnya", $back->text);
        $this->assertSame('ANS-0>3', $this->reply('3')->codes[0]);
        $this->assertSame(['STATUS'], $this->conversation(['9', '11'])->codes);
    }

    public function test_nine_after_held_answer_also_returns_to_submenu(): void
    {
        $this->holdFaq('TRF-007');
        $this->reply('Halo');

        $held = $this->conversation(['2', '1', '3']);
        $this->assertSame(['MSG-05', 'ANS-1>1>3'], $held->codes);
        $this->assertStringEndsWith(BotScript::answerFooter(english: true), $held->text);

        $this->assertSame(['M-09'], $this->reply('9')->codes);
        $this->assertSame('ANS-1>1>4', $this->reply('4')->codes[0]);
    }

    public function test_other_digits_after_answer_stay_invalid_and_repeat_footer(): void
    {
        $this->reply('Halo');
        $this->conversation(['2', '1', '1']);

        foreach (['7', '2', '12'] as $digit) {
            $invalid = $this->reply($digit);
            $this->assertContains($invalid->codes[0], ['MSG-01', 'MSG-02', 'MSG-03']);
            $this->conversation(['0', '2', '1', '1']);
        }

        $invalid = $this->reply('5');
        $this->assertSame('MSG-01', $invalid->codes[0]);
        $this->assertStringEndsWith(BotScript::answerFooter(english: true), $invalid->text, 'Footer baru (9/0/1) diulang.');
    }

    public function test_nine_resets_invalid_counter_so_msg03_is_not_reached_early(): void
    {
        $this->reply('Halo');
        $this->conversation(['2', '1', '1']);

        $this->assertSame('MSG-01', $this->reply('5')->codes[0]);
        $this->assertSame('MSG-02', $this->reply('5')->codes[0]);
        $this->assertSame(['M-09'], $this->reply('9')->codes);
        $this->assertSame('MSG-01', $this->reply('8')->codes[0], 'Hitungan salah input mulai dari nol setelah 9.');
        $this->assertSame(0, EscalationLog::count());
    }

    public function test_three_invalid_inputs_after_answer_still_offer_officer(): void
    {
        $this->reply('Halo');
        $this->conversation(['2', '1', '1']);

        $this->assertSame(['MSG-01', 'MSG-02', 'MSG-03'], [$this->reply('5')->codes[0], $this->reply('5')->codes[0], $this->reply('5')->codes[0]]);
        $this->assertSame('A1', EscalationLog::sole()->escalation_code);
    }

    public function test_nine_is_not_a_back_command_while_entering_application_number(): void
    {
        $this->reply('Halo');
        $this->conversation(['1', '11']);

        $invalid = $this->reply('9');
        $this->assertSame('MSG-01', $invalid->codes[0], 'Langkah cek status tidak punya "menu sebelumnya".');
        $this->assertStringEndsWith(BotScript::statusPrompt(), $invalid->text, 'Diminta nomor permohonan lagi.');
        $this->assertSame(['STATUS-PLACE'], $this->reply('1568000000012345')->codes);
        $this->assertSame('MSG-01', $this->reply('9')->codes[0], 'Tempat permohonan hanya 1-5.');
    }

    public function test_nine_while_queued_does_not_leave_the_queue(): void
    {
        $this->reply('Halo');
        $this->conversation(['2', '1', '1', '1']);
        $this->assertSame(1, ServiceTicket::open()->count());

        app(BotEngine::class)->handleQueued($this->chat->fresh(), $this->chat->messages()->create([
            'sender_type' => 'user', 'message_type' => 'text', 'message_text' => '9', 'sent_at' => now(),
        ]));

        $this->assertSame(1, ServiceTicket::open()->count(), '9 selama antrean tidak membatalkan atau keluar antrean.');
    }

    public function test_nine_in_submenu_without_answer_is_still_invalid(): void
    {
        // Submenu punya angka "kembali" sendiri (12/4/7/4); 9 di menu Paspor = Beda Data, di menu lain tidak valid.
        $this->reply('Halo');
        $this->assertSame('ANS-0>12', $this->conversation(['1', '9'])->codes[0]);
        $this->assertSame(['M-01'], $this->reply('9')->codes);

        $this->reply('0');
        $this->reply('2');
        $this->assertSame(['MSG-01', 'M-08'], $this->reply('9')->codes);
    }
}
