<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

class FaqCategoryTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    public function test_index_counts_faqs_per_category_including_empty(): void
    {
        $this->actingAsAdmin();
        $a = $this->makeCategory('A');
        $b = $this->makeCategory('B');
        $this->makeFaq($a->id);
        $this->makeFaq($a->id);

        $categories = $this->get(route('kategori-faq.index'))->assertOk()->viewData('categories');

        $this->assertSame(2, (int) $categories->firstWhere('id', $a->id)->total_faq);
        $this->assertSame(0, (int) $categories->firstWhere('id', $b->id)->total_faq);
    }

    public function test_create_form_renders(): void
    {
        $this->actingAsAdmin();

        $this->get(route('kategori-faq.create'))->assertOk();
    }

    public function test_store_active_category(): void
    {
        $this->actingAsAdmin();

        $this->post(route('kategori-faq.store'), [
            'namaKategori' => 'Visa',
            'deskripsiKategori' => 'Layanan visa',
            'statusKategori' => 'Aktif',
        ])->assertRedirect(route('kategori-faq.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('faq_categories', ['name' => 'Visa', 'description' => 'Layanan visa', 'is_active' => 1]);
    }

    public function test_store_inactive_category_without_description(): void
    {
        $this->actingAsAdmin();

        $this->post(route('kategori-faq.store'), ['namaKategori' => 'Lama', 'statusKategori' => 'Nonaktif']);

        $this->assertDatabaseHas('faq_categories', ['name' => 'Lama', 'description' => null, 'is_active' => 0]);
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing name' => [['namaKategori' => ''], 'namaKategori'],
            'name too long' => [['namaKategori' => str_repeat('a', 101)], 'namaKategori'],
            'missing status' => [['statusKategori' => ''], 'statusKategori'],
            'invalid status' => [['statusKategori' => 'aktif'], 'statusKategori'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_store_rejects_invalid_input(array $override, string $field): void
    {
        $this->actingAsAdmin();

        $this->post(route('kategori-faq.store'), array_merge(['namaKategori' => 'X', 'statusKategori' => 'Aktif'], $override))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('faq_categories', 0);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        $this->actingAsAdmin();
        $this->makeCategory('Paspor');

        $this->post(route('kategori-faq.store'), ['namaKategori' => 'Paspor', 'statusKategori' => 'Aktif'])
            ->assertSessionHasErrors(['namaKategori' => 'Nama kategori sudah digunakan.']);
    }

    public function test_store_accepts_name_of_exactly_100_chars(): void
    {
        $this->actingAsAdmin();

        $this->post(route('kategori-faq.store'), ['namaKategori' => str_repeat('a', 100), 'statusKategori' => 'Aktif'])
            ->assertSessionHasNoErrors();
    }

    public function test_update_renames_category(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory('Lama');

        $this->put(route('kategori-faq.update', $cat->id), ['namaKategori' => 'Baru'])
            ->assertRedirect(route('kategori-faq.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('faq_categories', ['id' => $cat->id, 'name' => 'Baru']);
    }

    public function test_update_with_own_name_is_allowed(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory('Sama');

        $this->put(route('kategori-faq.update', $cat->id), ['namaKategori' => 'Sama'])
            ->assertSessionHasNoErrors();
    }

    public function test_update_rejects_name_used_by_other_category(): void
    {
        $this->actingAsAdmin();
        $this->makeCategory('Satu');
        $two = $this->makeCategory('Dua');

        $this->put(route('kategori-faq.update', $two->id), ['namaKategori' => 'Satu'])
            ->assertSessionHasErrors('namaKategori');
    }

    public function test_update_rejects_empty_name(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->put(route('kategori-faq.update', $cat->id), ['namaKategori' => ''])
            ->assertSessionHasErrors('namaKategori');
    }

    public function test_update_unknown_category_is_404(): void
    {
        $this->actingAsAdmin();

        $this->put(route('kategori-faq.update', 999), ['namaKategori' => 'X'])->assertNotFound();
    }

    public function test_destroy_removes_category_and_orphans_its_faqs(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();
        $faqId = $this->makeFaq($cat->id);

        $this->delete(route('kategori-faq.destroy', $cat->id))
            ->assertRedirect(route('kategori-faq.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('faq_categories', ['id' => $cat->id]);
        $this->assertNull(DB::table('faqs')->find($faqId)->category_id);
    }

    public function test_destroy_unknown_category_is_404(): void
    {
        $this->actingAsAdmin();

        $this->delete(route('kategori-faq.destroy', 999))->assertNotFound();
    }
}
