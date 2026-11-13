<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwitchBotEngineTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/kancil-env-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/.env', "APP_NAME=x\nBOT_ENGINE=balesotomatis\nLLM_MODEL=gpt-5-nano\n");
        $this->app->useEnvironmentPath($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/.env');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function env(): string
    {
        return file_get_contents($this->dir.'/.env');
    }

    public function test_switch_to_sumopod_and_back(): void
    {
        $this->artisan('kancil:mode', ['mode' => 'sumopod'])
            ->expectsOutputToContain('BOT_ENGINE=laravel tersimpan.')
            ->expectsOutputToContain('MATIKAN "AI Powered"')
            ->assertSuccessful();
        $this->assertSame("APP_NAME=x\nBOT_ENGINE=laravel\nLLM_MODEL=gpt-5-nano\nLLM_ENABLED=true\n", $this->env());
        $this->assertSame('laravel', config('kancil.bot_engine'));

        $this->artisan('kancil:mode', ['mode' => 'balesotomatis'])
            ->expectsOutputToContain('NYALAKAN lagi "AI Powered"')
            ->assertSuccessful();
        $this->assertStringContainsString("BOT_ENGINE=balesotomatis\n", $this->env());
    }

    public function test_adds_key_when_missing(): void
    {
        file_put_contents($this->dir.'/.env', "APP_NAME=x\n");

        $this->artisan('kancil:mode', ['mode' => 'laravel'])->assertSuccessful();

        $this->assertSame("APP_NAME=x\nBOT_ENGINE=laravel\nLLM_ENABLED=true\n", $this->env());
    }

    public function test_sumopod_turns_ai_on_and_warns_when_key_missing(): void
    {
        file_put_contents($this->dir.'/.env', "BOT_ENGINE=balesotomatis\nLLM_ENABLED=false\nLLM_API_KEY=\n");
        config(['llm.api_key' => null]);

        $this->artisan('kancil:mode', ['mode' => 'sumopod'])
            ->expectsOutputToContain('LLM_ENABLED=true tersimpan')
            ->expectsOutputToContain('LLM_API_KEY kosong')
            ->assertSuccessful();

        $this->assertSame("BOT_ENGINE=laravel\nLLM_ENABLED=true\nLLM_API_KEY=\n", $this->env());
        $this->assertTrue(config('llm.enabled'));
    }

    public function test_switching_to_balesotomatis_leaves_ai_setting_alone(): void
    {
        file_put_contents($this->dir.'/.env', "BOT_ENGINE=laravel\nLLM_ENABLED=true\n");

        $this->artisan('kancil:mode', ['mode' => 'balesotomatis'])->assertSuccessful();

        $this->assertSame("BOT_ENGINE=balesotomatis\nLLM_ENABLED=true\n", $this->env());
    }

    public function test_status_shows_whether_sumopod_ai_is_active(): void
    {
        config(['kancil.bot_engine' => 'laravel', 'llm.enabled' => false, 'llm.api_key' => 'sk-x']);
        $this->artisan('kancil:mode')->expectsOutputToContain('AI SumoPod: NONAKTIF')->assertSuccessful();

        config(['llm.enabled' => true]);
        $this->artisan('kancil:mode')->expectsOutputToContain('AI SumoPod: aktif')->assertSuccessful();

        config(['llm.api_key' => null]);
        $this->artisan('kancil:mode')->expectsOutputToContain('AI SumoPod: NONAKTIF')->assertSuccessful();
    }

    public function test_show_current_mode_and_reject_unknown(): void
    {
        config(['kancil.bot_engine' => 'laravel']);
        $this->artisan('kancil:mode')->expectsOutputToContain('Mode sekarang: sumopod')->assertSuccessful();

        $this->artisan('kancil:mode', ['mode' => 'openai'])->assertFailed();
        $this->assertStringContainsString('BOT_ENGINE=balesotomatis', $this->env());
    }
}
