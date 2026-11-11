<?php

namespace Tests\Unit;

use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmClientTest extends TestCase
{
    private function client(array $overrides = []): LlmClient
    {
        return new LlmClient(array_merge([
            'enabled' => true,
            'api_key' => 'sk-test',
            'base_url' => 'https://ai.sumopod.com/v1',
            'model' => 'gpt-5-nano',
            'timeout' => 10,
            'max_completion_tokens' => 800,
            'reasoning_effort' => 'minimal',
        ], $overrides));
    }

    public function test_enabled_requires_flag_and_key(): void
    {
        $this->assertTrue($this->client()->isEnabled());
        $this->assertFalse($this->client(['enabled' => false])->isEnabled());
        $this->assertFalse($this->client(['api_key' => ''])->isEnabled());
        $this->assertFalse($this->client(['api_key' => null])->isEnabled());
    }

    public function test_chat_posts_openai_compatible_request(): void
    {
        Http::fake(['*' => Http::response([
            'model' => 'gpt-5-nano-2025',
            'choices' => [['message' => ['content' => '{"a":1}']]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3],
        ])]);

        $response = $this->client()->chat([['role' => 'user', 'content' => 'hai']]);

        $this->assertSame('{"a":1}', $response->content);
        $this->assertSame('gpt-5-nano-2025', $response->model);
        $this->assertSame(12, $response->promptTokens);
        $this->assertSame(3, $response->completionTokens);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://ai.sumopod.com/v1/chat/completions'
            && $r->method() === 'POST'
            && $r->hasHeader('Authorization', 'Bearer sk-test')
            && $r['model'] === 'gpt-5-nano'
            && $r['max_completion_tokens'] === 800
            && $r['reasoning_effort'] === 'minimal'
            && $r['response_format'] === ['type' => 'json_object']
            && $r['messages'] === [['role' => 'user', 'content' => 'hai']]);
    }

    public function test_optional_parameters_are_omitted(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'teks']]]])]);

        $this->client(['reasoning_effort' => ''])->chat([['role' => 'user', 'content' => 'x']], json: false);

        Http::assertSent(fn (Request $r) => ! isset($r['reasoning_effort']) && ! isset($r['response_format']) && ! isset($r['temperature']));
    }

    public function test_disabled_client_throws_without_http_call(): void
    {
        Http::fake();
        $this->expectException(LlmException::class);

        try {
            $this->client(['api_key' => null])->chat([]);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_http_error_is_reported(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Insufficient balance']], 402)]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->client()->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_empty_content_is_an_error(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '  ']]]])]);

        $this->expectException(LlmException::class);
        $this->client()->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_connection_error_is_wrapped(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('Gagal terhubung');
        $this->client()->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_balance_uses_v2_user_info(): void
    {
        Http::fake(['https://ai.sumopod.com/v2/user/info' => Http::response(['spend' => 47.50447564881963, 'max_budget' => 99.3])]);

        $balance = $this->client()->balance();

        $this->assertEqualsWithDelta(51.795524, $balance['balance'], 0.000001);
        $this->assertSame(99.3, $balance['max_budget']);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->hasHeader('Authorization', 'Bearer sk-test'));
    }

    public function test_balance_without_budget_and_failures(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['spend' => 1.5, 'max_budget' => null])
            ->push([], 401)]);

        $this->assertNull($this->client()->balance()['balance']);

        $this->expectException(LlmException::class);
        $this->client()->balance();
    }

    public function test_balance_requires_key(): void
    {
        $this->expectException(LlmException::class);
        $this->client(['api_key' => null])->balance();
    }
}
