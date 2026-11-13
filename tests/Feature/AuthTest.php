<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_renders_for_guest_with_no_cache_headers(): void
    {
        $response = $this->get('/login')->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_authenticated_user_is_redirected_away_from_login_page(): void
    {
        $this->actingAsAdmin();

        $this->get('/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_with_username(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['username' => 'admin', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_with_email(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['username' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_redirects_to_intended_url(): void
    {
        $this->makeUser();

        $this->get('/faq')->assertRedirect('/login');
        $this->post('/login', ['username' => 'admin', 'password' => 'password'])
            ->assertRedirect(route('faq.index'));
    }

    public function test_login_with_remember_sets_remember_token(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['username' => 'admin', 'password' => 'password', 'remember' => '1']);

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_wrong_password_is_rejected_and_keeps_username(): void
    {
        $this->makeUser();

        $this->from('/login')
            ->post('/login', ['username' => 'admin', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHas('message', 'Username atau kata sandi tidak sesuai.')
            ->assertSessionHasInput('username', 'admin');

        $this->assertGuest();
    }

    public function test_unknown_user_is_rejected(): void
    {
        $this->post('/login', ['username' => 'ghost', 'password' => 'password'])
            ->assertSessionHas('message');

        $this->assertGuest();
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $this->makeUser(['is_active' => false]);

        $this->post('/login', ['username' => 'admin', 'password' => 'password'])
            ->assertSessionHas('message');

        $this->assertGuest();
    }

    public function test_petugas_and_pimpinan_can_login(): void
    {
        foreach (['petugas', 'pimpinan'] as $role) {
            $user = $this->makeUser(['role' => $role, 'username' => $role, 'email' => "{$role}@example.com"]);

            $this->post('/login', ['username' => $role, 'password' => 'password'])
                ->assertRedirect(route('admin.dashboard'));

            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
    }

    public function test_login_requires_both_fields(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors([
                'username' => 'Username wajib diisi.',
                'password' => 'Kata sandi wajib diisi.',
            ]);
    }

    public function test_logout_ends_session(): void
    {
        $this->actingAsAdmin();

        $this->post('/logout')
            ->assertRedirect(route('login'))
            ->assertSessionHas('message', 'Anda berhasil keluar dari sistem.');

        $this->assertGuest();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_seeded_admin_can_login(): void
    {
        $this->seed();

        $this->post('/login', ['username' => 'admin', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs(User::where('username', 'admin')->first());
    }
}
