<?php

namespace Tests\Unit;

use App\Models\Complaint;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    public function test_active_scope_filters_inactive_categories(): void
    {
        $this->makeCategory('On');
        $this->makeCategory('Off', false);

        $this->assertSame(['On'], FaqCategory::active()->pluck('name')->all());
    }

    public function test_category_casts_is_active_to_bool(): void
    {
        $this->assertTrue($this->makeCategory()->fresh()->is_active);
    }

    public function test_user_password_is_hashed_and_hidden(): void
    {
        $user = $this->makeUser();

        $this->assertNotSame('password', $user->password);
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertTrue($user->is_active);
    }

    public function test_faq_relations(): void
    {
        $user = $this->makeUser();
        $cat = $this->makeCategory();
        $id = $this->makeFaq($cat->id, ['verified_by' => $user->id]);
        \DB::table('faq_keywords')->insert(['faq_id' => $id, 'keyword' => 'k', 'created_at' => now()]);

        $faq = Faq::with(['category', 'keywords', 'verifier'])->find($id);

        $this->assertSame($cat->id, $faq->category->id);
        $this->assertSame('k', $faq->keywords->first()->keyword);
        $this->assertSame($user->id, $faq->verifier->id);
        $this->assertCount(1, $cat->faqs);
        $this->assertCount(1, $user->verifiedFaqs);
    }

    public function test_complaint_and_conversation_relations(): void
    {
        $user = $this->makeUser();
        $convId = $this->makeConversation(['assigned_to' => $user->id]);
        $complaint = Complaint::create([
            'ticket_number' => 'T-1', 'conversation_id' => $convId, 'complaint_text' => 'x',
            'user_name' => 'Budi', 'whatsapp_number' => '6281',
            'assigned_to' => $user->id, 'submitted_at' => now(),
        ]);

        $this->assertSame($convId, $complaint->conversation->id);
        $this->assertSame($user->id, $complaint->assignedPetugas->id);
        $this->assertCount(1, $user->assignedComplaints);
        $this->assertCount(1, $user->assignedConversations);
        $this->assertSame('Menunggu', $complaint->fresh()->status);
    }
}
