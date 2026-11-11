<?php

namespace Tests\Feature;

use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_admin_can_login_and_logout(): void
    {
        $this->admin();

        $this->post('/login', ['username' => 'admin', 'password' => 'wrong'])
            ->assertSessionHas('message');
        $this->assertGuest();

        $this->post('/login', ['username' => 'admin', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_authenticated_pages_render(): void
    {
        $this->actingAs($this->admin());

        foreach (['admin.dashboard', 'faq.index', 'faq.create', 'kategori-faq.index',
                  'kategori-faq.create', 'percakapan', 'pengaduan', 'laporan', 'pengaturan'] as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_faq_crud(): void
    {
        $this->actingAs($this->admin());
        $cat = FaqCategory::create(['name' => 'Paspor', 'is_active' => true]);

        $this->post(route('faq.store'), [
            'kategori' => $cat->id,
            'status' => 'draft',
            'pertanyaan' => 'Cara buat paspor?',
            'kata_kunci' => 'paspor, buat',
            'jawaban' => 'Daftar online.',
        ])->assertRedirect(route('faq.index'));

        $this->assertDatabaseHas('faqs', ['question' => 'Cara buat paspor?']);
        $this->assertDatabaseCount('faq_keywords', 2);

        $id = \DB::table('faqs')->value('id');
        $this->get(route('faq.edit', $id))->assertOk();
        $this->delete(route('faq.destroy', $id))->assertRedirect(route('faq.index'));
        $this->assertDatabaseCount('faqs', 0);
    }

    public function test_faq_category_create(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('kategori-faq.store'), [
            'namaKategori' => 'Visa',
            'statusKategori' => 'Aktif',
        ])->assertRedirect(route('kategori-faq.index'));

        $this->assertDatabaseHas('faq_categories', ['name' => 'Visa', 'is_active' => 1]);
    }
}
