<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

/**
 * Akses halaman per role: Admin (semua), Petugas (percakapan), Pimpinan (pengaduan & laporan).
 */
class RoleAccessTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    /** @return array<string, array{string, string, int}> */
    public static function matrix(): array
    {
        $routes = ['/admin/dashboard', '/percakapan', '/pengaduan', '/laporan', '/pengaturan', '/faq', '/faq/create', '/kategori-faq', '/kategori-faq/create'];
        $allowed = [
            'admin' => $routes,
            'petugas' => ['/admin/dashboard', '/percakapan', '/pengaduan', '/pengaturan'],
            'pimpinan' => ['/admin/dashboard', '/percakapan', '/pengaduan', '/laporan', '/pengaturan'],
        ];

        $cases = [];
        foreach ($allowed as $role => $ok) {
            foreach ($routes as $uri) {
                $cases["{$role} {$uri}"] = [$role, $uri, in_array($uri, $ok, true) ? 200 : 403];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_page_access(string $role, string $uri, int $status): void
    {
        $this->actingAsAdmin(['role' => $role]);

        $this->get($uri)->assertStatus($status);
    }

    public function test_petugas_cannot_write_faq_or_categories(): void
    {
        $this->actingAsAdmin(['role' => 'petugas']);

        $this->post('/faq', [])->assertForbidden();
        $this->put('/faq/1', [])->assertForbidden();
        $this->delete('/faq/1')->assertForbidden();
        $this->post('/kategori-faq', [])->assertForbidden();
        $this->delete('/kategori-faq/1')->assertForbidden();
    }

    public function test_sidebar_hides_menu_items_by_role(): void
    {
        $this->actingAsAdmin(['role' => 'petugas']);
        $html = $this->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('>Database FAQ<', $html);
        $this->assertStringNotContainsString('>Kategori FAQ<', $html);
        $this->assertStringNotContainsString('>Laporan<', $html);
        $this->assertStringContainsString('>Percakapan<', $html);
        $this->assertStringNotContainsString('Ekspor PDF', $html);
    }

    public function test_admin_sees_full_sidebar(): void
    {
        $this->actingAsAdmin();
        $html = $this->get('/admin/dashboard')->getContent();

        foreach (['Dashboard', 'Percakapan', 'Database FAQ', 'Kategori FAQ', 'Pengaduan', 'Laporan', 'Pengaturan'] as $label) {
            $this->assertStringContainsString(">{$label}<", $html, $label);
        }
    }

    public function test_inactive_users_cannot_login_regardless_of_role(): void
    {
        $this->makeUser(['role' => 'pimpinan', 'is_active' => false]);

        $this->post('/login', ['username' => 'admin', 'password' => 'password'])->assertSessionHas('message');
        $this->assertGuest();
    }
}
