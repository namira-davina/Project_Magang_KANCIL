<?php

namespace Tests\Unit;

use App\Services\BalesOtomatis\BalesOtomatisClient;
use App\Services\BalesOtomatis\BalesOtomatisException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BalesOtomatisClientTest extends TestCase
{
    private function client(array $overrides = []): BalesOtomatisClient
    {
        return new BalesOtomatisClient(array_merge([
            'base_url' => 'https://api.balesotomatis.co.id/public/v1',
            'api_key' => 'KODE',
            'number_id' => 'NUM',
            'licenses_key' => null,
            'channel' => 'nonresmi',
            'timeout' => 5,
            'enable_typing' => false,
        ], $overrides));
    }

    public function test_configuration_checks(): void
    {
        $this->assertTrue($this->client()->isConfigured());
        $this->assertFalse($this->client(['api_key' => ''])->isConfigured());
        $this->assertFalse($this->client(['number_id' => null])->isConfigured());
        $this->assertFalse($this->client(['channel' => 'waba'])->isConfigured());
        $this->assertTrue($this->client(['channel' => 'waba', 'licenses_key' => 'LIC'])->isConfigured());
        $this->assertSame(['NUM'], $this->client()->deviceKeys());
        $this->assertSame(['NUM', 'LIC'], $this->client(['licenses_key' => 'LIC'])->deviceKeys());
        $this->assertSame([], $this->client(['number_id' => null])->deviceKeys());
    }

    public function test_send_text_posts_credentials_in_body(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'message' => 'success', 'data' => ['message_id' => 'MSG-9']])]);

        $id = $this->client(['enable_typing' => true])->sendText('628123', 'Halo', 'uuid-1', 'QUOTED');

        $this->assertSame('MSG-9', $id);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.balesotomatis.co.id/public/v1/send_personal_message'
            && $r->method() === 'POST'
            && $r->data() === [
                'api_key' => 'KODE', 'number_id' => 'NUM', 'phone_no' => '628123', 'message' => 'Halo',
                'message_uuid' => 'uuid-1', 'quoted_msg' => 'QUOTED', 'enable_typing' => 1,
            ]);
    }

    public function test_optional_fields_are_omitted(): void
    {
        Http::fake(['*' => Http::response(['code' => 200])]);

        $this->assertNull($this->client()->sendText('628123', 'Halo'));

        Http::assertSent(fn (Request $r) => array_keys($r->data()) === ['api_key', 'number_id', 'phone_no', 'message']);
    }

    public function test_waba_uses_meta_endpoint_and_secret_key(): void
    {
        Http::fake(['*' => Http::response(['code' => '200', 'message_id' => 'wamid.1'])]);

        $id = $this->client(['channel' => 'waba', 'licenses_key' => 'LIC'])->sendText('628123', 'Halo', 'uuid');

        $this->assertSame('wamid.1', $id);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/send_meta_personal_message')
            && $r->data() === ['secret_key' => 'KODE', 'licenses_key' => 'LIC', 'phone_no' => '628123', 'message' => 'Halo']);
    }

    public function test_send_file_and_info(): void
    {
        Http::fake([
            '*/send_personal_file' => Http::response(['code' => 200, 'data' => ['id' => 'F-1']]),
            '*/info' => Http::response(['code' => 200, 'data' => ['number_id' => 'NUM', 'status' => 'connected']]),
        ]);

        $this->assertSame('F-1', $this->client()->sendFile('628123', 'https://x/y.pdf', 'Bukti'));
        $this->assertSame('connected', $this->client()->info()['data']['status']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/send_personal_file') && $r['file_url'] === 'https://x/y.pdf' && $r['message'] === 'Bukti');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/info') && $r->data() === ['api_key' => 'KODE']);
    }

    /** @return array<string, array{mixed, int, bool}> */
    public static function errorResponses(): array
    {
        return [
            'HTTP 200 tapi code 400' => [['code' => 400, 'message' => 'Parameter tidak valid'], 200, false],
            'batas per menit' => [['code' => 400, 'message' => 'Rate limit per minute exceeded'], 200, true],
            'kunci salah' => [['code' => 403, 'message' => 'Invalid api_key'], 200, false],
            'tidak ditemukan' => [['code' => 404, 'message' => 'Not found'], 200, false],
            'parameter kurang' => [['code' => 405, 'message' => 'Missing param'], 200, false],
            'timeout' => [['code' => 408, 'message' => 'Timeout'], 200, true],
            'HTTP 500 tanpa body' => [null, 500, true],
            'HTTP 429' => [[], 429, true],
        ];
    }

    #[DataProvider('errorResponses')]
    public function test_error_codes(?array $body, int $status, bool $retryable): void
    {
        Http::fake(['*' => Http::response($body, $status)]);

        try {
            $this->client()->sendText('628123', 'Halo');
            $this->fail('Harus melempar exception.');
        } catch (BalesOtomatisException $e) {
            $this->assertSame($retryable, $e->retryable);
            $this->assertStringContainsString('send_personal_message gagal', $e->getMessage());
        }
    }

    public function test_connection_error_is_retryable(): void
    {
        Http::fake(fn () => throw new ConnectionException('putus'));

        try {
            $this->client()->sendText('628123', 'Halo');
            $this->fail();
        } catch (BalesOtomatisException $e) {
            $this->assertTrue($e->retryable);
            $this->assertNull($e->apiCode);
        }
    }

    public function test_missing_api_key_throws_without_request(): void
    {
        Http::fake();

        $this->expectException(BalesOtomatisException::class);
        $this->expectExceptionMessage('BALESOTOMATIS_API_KEY');

        try {
            $this->client(['api_key' => null])->sendText('628123', 'Halo');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_container_builds_client_from_config(): void
    {
        config(['balesotomatis.api_key' => 'X', 'balesotomatis.number_id' => 'Y']);

        $this->assertTrue(app(BalesOtomatisClient::class)->isConfigured());
    }
}
