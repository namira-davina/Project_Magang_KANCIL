<?php

namespace App\Services;

use App\Support\Keywords;
use Illuminate\Support\Facades\DB;

class FaqService
{
    public const STATUS_VERIFIED = 'terverifikasi';

    /**
     * Create a FAQ (inactive until reviewed) together with its keywords.
     *
     * @param  array<string, mixed>  $data  validated FaqRequest data
     */
    public function create(array $data, ?int $userId): int
    {
        return DB::transaction(function () use ($data, $userId) {
            $verified = $data['status'] === self::STATUS_VERIFIED;

            $id = DB::table('faqs')->insertGetId([
                ...$this->attributes($data),
                'verified_by' => $verified ? $userId : null,
                'verified_at' => $verified ? now() : null,
                'is_active' => 0,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncKeywords($id, $data['kata_kunci']);

            return $id;
        });
    }

    /**
     * Update a FAQ. A FAQ that is not verified is always deactivated.
     *
     * @param  array<string, mixed>  $data  validated FaqRequest data
     */
    public function update(object $faq, array $data, ?int $userId): void
    {
        DB::transaction(function () use ($faq, $data, $userId) {
            $verified = $data['status'] === self::STATUS_VERIFIED;
            $active = isset($data['aktif']) ? (bool) $data['aktif'] : (bool) $faq->is_active;

            DB::table('faqs')->where('id', $faq->id)->update([
                ...$this->attributes($data),
                'verified_by' => $verified ? $userId : null,
                'verified_at' => $verified ? ($faq->verified_at ?? now()) : null,
                // Hanya FAQ terverifikasi yang boleh aktif; centang "Aktif" di form edit mengaturnya.
                'is_active' => $verified && $active ? 1 : 0,
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            $this->syncKeywords($faq->id, $data['kata_kunci']);
        });
    }

    /**
     * Delete a FAQ; messages that referenced it are kept, detached.
     */
    public function delete(int|string $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table('messages')->where('faq_id', $id)->update(['faq_id' => null]);
            DB::table('faq_keywords')->where('faq_id', $id)->delete();
            DB::table('faqs')->where('id', $id)->delete();
        });
    }

    /** @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'category_id' => $data['kategori'],
            'question' => $data['pertanyaan'],
            'answer' => $data['jawaban'],
            'source' => $data['tautan'] ?? null,
            'source_url' => $data['tautan'] ?? null,
            'verification_status' => $data['status'],
        ];
    }

    private function syncKeywords(int|string $faqId, string $raw): void
    {
        DB::table('faq_keywords')->where('faq_id', $faqId)->delete();

        $rows = array_map(fn ($keyword) => [
            'faq_id' => $faqId,
            'keyword' => $keyword,
            'created_at' => now(),
        ], Keywords::parse($raw));

        if ($rows) {
            DB::table('faq_keywords')->insert($rows);
        }
    }
}
