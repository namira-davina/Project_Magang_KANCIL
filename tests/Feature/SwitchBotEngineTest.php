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
        $this->assertSame("APP_NAME=x\nBOT_ENGINE=laravel\nLLM_MODEL=gpt-5-nano\n", $this->env());
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

        $this->assertSame("APP_NAME=x\nBOT_ENGINE=laravel\n", $this->env());
    }

    public function test_show_current_mode_and_reject_unknown(): void
    {
        config(['kancil.bot_engine' => 'laravel']);
        $this->artisan('kancil:mode')->expectsOutputToContain('Mode sekarang: sumopod')->assertSuccessful();

        $this->artisan('kancil:mode', ['mode' => 'openai'])->assertFailed();
        $this->assertStringContainsString('BOT_ENGINE=balesotomatis', $this->env());
    }
}
