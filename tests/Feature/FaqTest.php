<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    public function test_index_lists_faqs_with_category_name_and_only_active_categories(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory('Paspor');
        $this->makeCategory('Hidden', false);
        $this->makeFaq($cat->id, ['question' => 'Q1']);

        $response = $this->get(route('faq.index'))->assertOk()->assertSee('Q1');

        $faqs = $response->viewData('faqs');
        $this->assertCount(1, $faqs);
        $this->assertSame('Paspor', $faqs->first()->category_name);
        $this->assertSame(['Paspor'], $response->viewData('categories')->pluck('name')->all());
    }

    public function test_index_includes_faq_without_category(): void
    {
        $this->actingAsAdmin();
        $this->makeFaq(0 + $this->makeCategory()->id, ['category_id' => null]);

        $faqs = $this->get(route('faq.index'))->viewData('faqs');

        $this->assertNull($faqs->first()->category_name);
    }

    public function test_create_form_only_offers_active_categories(): void
    {
        $this->actingAsAdmin();
        $this->makeCategory('Aktif');
        $this->makeCategory('Mati', false);

        $names = $this->get(route('faq.create'))->assertOk()->viewData('categories')->pluck('name')->all();

        $this->assertSame(['Aktif'], $names);
    }

    public function test_store_draft_faq_is_inactive_and_unverified(): void
    {
        $admin = $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->post(route('faq.store'), $this->validFaqPayload($cat->id))
            ->assertRedirect(route('faq.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('faqs', [
            'category_id' => $cat->id,
            'question' => 'Cara buat paspor?',
            'answer' => 'Daftar online.',
            'source_url' => 'https://imigrasi.go.id/paspor',
            'verification_status' => 'draft',
            'verified_by' => null,
            'verified_at' => null,
            'is_active' => 0,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_store_verified_faq_records_verifier_but_stays_inactive(): void
    {
        $admin = $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->post(route('faq.store'), $this->validFaqPayload($cat->id, ['status' => 'terverifikasi']));

        $faq = DB::table('faqs')->first();
        $this->assertSame($admin->id, (int) $faq->verified_by);
        $this->assertNotNull($faq->verified_at);
        $this->assertEquals(0, $faq->is_active);
    }

    public function test_store_without_link_saves_null_source(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->post(route('faq.store'), $this->validFaqPayload($cat->id, ['tautan' => null]));

        $this->assertDatabaseHas('faqs', ['source' => null, 'source_url' => null]);
    }

    public function test_store_trims_and_deduplicates_keywords(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->post(route('faq.store'), $this->validFaqPayload($cat->id, ['kata_kunci' => ' paspor , buat,, paspor ,']));

        $this->assertSame(['paspor', 'buat'], DB::table('faq_keywords')->orderBy('id')->pluck('keyword')->all());
    }

    public function test_store_with_only_separators_in_keywords_saves_none(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->post(route('faq.store'), $this->validFaqPayload($cat->id, ['kata_kunci' => ' , , ']))
            ->assertRedirect(route('faq.index'));

        $this->assertDatabaseCount('faq_keywords', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing category' => [['kategori' => ''], 'kategori'],
            'unknown category' => [['kategori' => 9999], 'kategori'],
            'missing status' => [['status' => ''], 'status'],
            'invalid status' => [['status' => 'aktif'], 'status'],
            'missing question' => [['pertanyaan' => ''], 'pertanyaan'],
            'question too long' => [['pertanyaan' => str_repeat('a', 256)], 'pertanyaan'],
            'missing keywords' => [['kata_kunci' => ''], 'kata_kunci'],
            'missing answer' => [['jawaban' => ''], 'jawaban'],
            'answer too long' => [['jawaban' => str_repeat('a', 1601)], 'jawaban'],
            'invalid url' => [['tautan' => 'bukan-url'], 'tautan'],
            'url too long' => [['tautan' => 'https://x.id/'.str_repeat('a', 260)], 'tautan'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_store_rejects_invalid_input(array $override, string $field): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->post(route('faq.store'), $this->validFaqPayload($cat->id, $override))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('faqs', 0);
    }

    public function test_store_accepts_boundary_lengths(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->post(route('faq.store'), $this->validFaqPayload($cat->id, [
            'pertanyaan' => str_repeat('a', 255),
            'jawaban' => str_repeat('b', 1600),
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('faqs', 1);
    }

    public function test_error_messages_are_in_indonesian(): void
    {
        $this->actingAsAdmin();

        $this->post(route('faq.store'), [])->assertSessionHasErrors([
            'kategori' => 'Kategori layanan wajib dipilih.',
            'pertanyaan' => 'Pertanyaan wajib diisi.',
            'kata_kunci' => 'Kata kunci wajib diisi.',
            'jawaban' => 'Jawaban wajib diisi.',
        ]);
    }

    public function test_edit_form_shows_keywords_joined(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();
        $id = $this->makeFaq($cat->id);
        DB::table('faq_keywords')->insert([
            ['faq_id' => $id, 'keyword' => 'a', 'created_at' => now()],
            ['faq_id' => $id, 'keyword' => 'b', 'created_at' => now()],
        ]);

        $this->get(route('faq.edit', $id))
            ->assertOk()
            ->assertViewHas('keywords', 'a, b');
    }

    public function test_edit_unknown_faq_is_404(): void
    {
        $this->actingAsAdmin();

        $this->get(route('faq.edit', 999))->assertNotFound();
    }

    public function test_update_replaces_fields_and_keywords(): void
    {
        $admin = $this->actingAsAdmin();
        $cat = $this->makeCategory();
        $other = $this->makeCategory('Visa');
        $id = $this->makeFaq($cat->id);
        DB::table('faq_keywords')->insert(['faq_id' => $id, 'keyword' => 'lama', 'created_at' => now()]);

        $this->put(route('faq.update', $id), $this->validFaqPayload($other->id, [
            'pertanyaan' => 'Baru?',
            'kata_kunci' => 'baru, kata',
        ]))->assertRedirect(route('faq.index'))->assertSessionHas('success', 'Data FAQ berhasil diperbarui.');

        $this->assertDatabaseHas('faqs', ['id' => $id, 'category_id' => $other->id, 'question' => 'Baru?', 'updated_by' => $admin->id]);
        $this->assertSame(['baru', 'kata'], DB::table('faq_keywords')->where('faq_id', $id)->orderBy('id')->pluck('keyword')->all());
    }

    public function test_update_verified_keeps_active_flag_and_original_verified_at(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();
        $at = now()->subDays(3)->startOfSecond();
        $id = $this->makeFaq($cat->id, ['is_active' => 1, 'verified_at' => $at, 'verification_status' => 'terverifikasi']);

        $this->put(route('faq.update', $id), $this->validFaqPayload($cat->id, ['status' => 'terverifikasi']));

        $faq = DB::table('faqs')->find($id);
        $this->assertEquals(1, $faq->is_active);
        $this->assertSame($at->format('Y-m-d H:i:s'), substr($faq->verified_at, 0, 19));
    }

    public function test_update_to_unverified_deactivates_and_clears_verification(): void
    {
        $admin = $this->actingAsAdmin();
        $cat = $this->makeCategory();
        $id = $this->makeFaq($cat->id, ['is_active' => 1, 'verified_by' => $admin->id, 'verified_at' => now(), 'verification_status' => 'terverifikasi']);

        $this->put(route('faq.update', $id), $this->validFaqPayload($cat->id, ['status' => 'perlu_verifikasi']));

        $this->assertDatabaseHas('faqs', ['id' => $id, 'is_active' => 0, 'verified_by' => null, 'verified_at' => null]);
    }

    public function test_update_validation_failure_changes_nothing(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();
        $id = $this->makeFaq($cat->id, ['question' => 'Asli']);

        $this->put(route('faq.update', $id), $this->validFaqPayload($cat->id, ['pertanyaan' => '']))
            ->assertSessionHasErrors('pertanyaan');

        $this->assertDatabaseHas('faqs', ['id' => $id, 'question' => 'Asli']);
    }

    public function test_update_unknown_faq_is_404(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->put(route('faq.update', 999), $this->validFaqPayload($cat->id))->assertNotFound();
    }

    public function test_destroy_removes_faq_and_keywords_but_keeps_messages(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();
        $id = $this->makeFaq($cat->id);
        DB::table('faq_keywords')->insert(['faq_id' => $id, 'keyword' => 'k', 'created_at' => now()]);
        $messageId = $this->makeMessage($this->makeConversation(), 'bot', ['faq_id' => $id]);

        $this->delete(route('faq.destroy', $id))
            ->assertRedirect(route('faq.index'))
            ->assertSessionHas('success', 'FAQ berhasil dihapus.');

        $this->assertDatabaseMissing('faqs', ['id' => $id]);
        $this->assertDatabaseCount('faq_keywords', 0);
        $this->assertDatabaseHas('messages', ['id' => $messageId, 'faq_id' => null]);
    }

    public function test_destroy_unknown_faq_is_404(): void
    {
        $this->actingAsAdmin();

        $this->delete(route('faq.destroy', 999))->assertNotFound();
    }
}
