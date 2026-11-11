<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    public static function protectedGetRoutes(): array
    {
        return [
            ['/admin/dashboard'], ['/faq'], ['/faq/create'], ['/faq/1/edit'],
            ['/kategori-faq'], ['/kategori-faq/create'],
            ['/percakapan'], ['/pengaduan'], ['/laporan'], ['/pengaturan'],
        ];
    }

    #[DataProvider('protectedGetRoutes')]
    public function test_guest_is_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    public function test_guest_cannot_use_write_endpoints(): void
    {
        $this->post('/faq', [])->assertRedirect('/login');
        $this->put('/faq/1', [])->assertRedirect('/login');
        $this->delete('/faq/1')->assertRedirect('/login');
        $this->post('/kategori-faq', [])->assertRedirect('/login');
        $this->put('/kategori-faq/1', [])->assertRedirect('/login');
        $this->delete('/kategori-faq/1')->assertRedirect('/login');
        $this->post('/pengaturan/profil', [])->assertRedirect('/login');
        $this->post('/pengaturan/password', [])->assertRedirect('/login');
    }

    public function test_authenticated_pages_render_with_no_cache_headers(): void
    {
        $this->actingAsAdmin();

        foreach (['admin.dashboard', 'faq.index', 'faq.create', 'kategori-faq.index',
            'kategori-faq.create', 'percakapan', 'pengaduan', 'laporan', 'pengaturan'] as $name) {
            $response = $this->get(route($name))->assertOk();
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), $name);
        }
    }

    public function test_unknown_route_returns_404(): void
    {
        $this->get('/does-not-exist')->assertNotFound();
    }
}
