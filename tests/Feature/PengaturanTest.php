<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

class PengaturanTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    private function profile(array $override = []): array
    {
        return array_merge([
            'name' => 'Nama Baru',
            'email' => 'baru@example.com',
            'username' => 'baru',
        ], $override);
    }

    public function test_page_renders(): void
    {
        $this->actingAsAdmin();

        $this->get(route('pengaturan'))->assertOk()->assertSee('admin@example.com');
    }

    public function test_update_profile(): void
    {
        $user = $this->actingAsAdmin();

        $this->post(route('pengaturan.profil'), $this->profile())
            ->assertRedirect()
            ->assertSessionHas('success', 'Profil berhasil diperbarui.');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nama Baru', 'email' => 'baru@example.com', 'username' => 'baru']);
    }

    public function test_update_profile_keeping_own_email_and_username_is_allowed(): void
    {
        $this->actingAsAdmin();

        $this->post(route('pengaturan.profil'), $this->profile(['email' => 'admin@example.com', 'username' => 'admin']))
            ->assertSessionHasNoErrors();
    }

    public function test_update_profile_rejects_email_and_username_of_other_user(): void
    {
        $this->makeUser(['name' => 'Lain', 'username' => 'lain', 'email' => 'lain@example.com']);
        $this->actingAsAdmin(['username' => 'me', 'email' => 'me@example.com']);

        $this->post(route('pengaturan.profil'), $this->profile(['email' => 'lain@example.com', 'username' => 'lain']))
            ->assertSessionHasErrors(['email' => 'Email sudah digunakan.', 'username' => 'Username sudah digunakan.']);
    }

    public static function invalidProfiles(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'missing email' => [['email' => ''], 'email'],
            'bad email' => [['email' => 'bukan-email'], 'email'],
            'missing username' => [['username' => ''], 'username'],
            'name too long' => [['name' => str_repeat('a', 101)], 'name'],
            'username too long' => [['username' => str_repeat('a', 51)], 'username'],
            'email too long' => [['email' => 'a@'.str_repeat('b', 50).'.'.str_repeat('c', 50).'.com'], 'email'],
        ];
    }

    #[DataProvider('invalidProfiles')]
    public function test_update_profile_validation(array $override, string $field): void
    {
        $user = $this->actingAsAdmin();

        $this->post(route('pengaturan.profil'), $this->profile($override))->assertSessionHasErrors($field);

        $this->assertSame('Admin', $user->fresh()->name);
    }

    public function test_change_password(): void
    {
        $user = $this->actingAsAdmin();

        $this->post(route('pengaturan.password'), [
            'password_lama' => 'password',
            'password_baru' => 'newpassword1',
            'password_baru_confirmation' => 'newpassword1',
        ])->assertSessionHas('success', 'Password berhasil diubah.');

        $this->assertTrue(Hash::check('newpassword1', $user->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = $this->actingAsAdmin();

        $this->post(route('pengaturan.password'), [
            'password_lama' => 'salah',
            'password_baru' => 'newpassword1',
            'password_baru_confirmation' => 'newpassword1',
        ])->assertSessionHasErrors(['password_lama' => 'Password lama tidak sesuai.']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_change_password_rejects_short_or_unconfirmed_password(): void
    {
        $this->actingAsAdmin();

        $this->post(route('pengaturan.password'), [
            'password_lama' => 'password',
            'password_baru' => 'short',
            'password_baru_confirmation' => 'short',
        ])->assertSessionHasErrors(['password_baru' => 'Password baru minimal 8 karakter.']);

        $this->post(route('pengaturan.password'), [
            'password_lama' => 'password',
            'password_baru' => 'newpassword1',
            'password_baru_confirmation' => 'different1',
        ])->assertSessionHasErrors(['password_baru' => 'Konfirmasi password tidak cocok.']);
    }

    public function test_change_password_requires_fields(): void
    {
        $this->actingAsAdmin();

        $this->post(route('pengaturan.password'), [])
            ->assertSessionHasErrors(['password_lama', 'password_baru']);
    }
}
