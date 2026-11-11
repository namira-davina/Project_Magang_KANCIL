<?php

namespace Tests\Concerns;

use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait CreatesData
{
    protected function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ], $overrides));
    }

    protected function actingAsAdmin(array $overrides = []): User
    {
        $user = $this->makeUser($overrides);
        $this->actingAs($user);

        return $user;
    }

    protected function makeCategory(string $name = 'Paspor', bool $active = true): FaqCategory
    {
        return FaqCategory::create(['name' => $name, 'is_active' => $active]);
    }

    protected function makeFaq(int $categoryId, array $overrides = []): int
    {
        return DB::table('faqs')->insertGetId(array_merge([
            'category_id' => $categoryId,
            'question' => 'Pertanyaan?',
            'answer' => 'Jawaban.',
            'verification_status' => 'draft',
            'is_active' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function makeConversation(array $overrides = []): int
    {
        static $n = 0;

        return DB::table('conversations')->insertGetId(array_merge([
            'ticket_number' => 'KS-'.str_pad((string) ++$n, 6, '0', STR_PAD_LEFT),
            'whatsapp_number' => '6281234567890',
            'user_name' => 'Budi',
            'started_at' => now(),
            'last_message_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function makeMessage(int $conversationId, string $senderType, array $overrides = []): int
    {
        return DB::table('messages')->insertGetId(array_merge([
            'conversation_id' => $conversationId,
            'sender_type' => $senderType,
            'message_text' => 'halo',
            'sent_at' => now(),
            'created_at' => now(),
        ], $overrides));
    }

    protected function makeComplaint(string $status = 'Menunggu', array $overrides = []): int
    {
        static $n = 0;

        return DB::table('complaints')->insertGetId(array_merge([
            'ticket_number' => 'PG-'.str_pad((string) ++$n, 6, '0', STR_PAD_LEFT),
            'user_name' => 'Budi',
            'whatsapp_number' => '6281234567890',
            'complaint_text' => 'keluhan',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function makeEscalation(int $conversationId, array $overrides = []): int
    {
        return DB::table('escalation_logs')->insertGetId(array_merge([
            'conversation_id' => $conversationId,
            'escalation_type' => 'user_request',
            'created_at' => now(),
        ], $overrides));
    }

    protected function validFaqPayload(int $categoryId, array $overrides = []): array
    {
        return array_merge([
            'kategori' => $categoryId,
            'status' => 'draft',
            'pertanyaan' => 'Cara buat paspor?',
            'kata_kunci' => 'paspor, buat',
            'jawaban' => 'Daftar online.',
            'tautan' => 'https://imigrasi.go.id/paspor',
        ], $overrides);
    }
}
