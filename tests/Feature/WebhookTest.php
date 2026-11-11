<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingChat;
use App\Jobs\ProcessOutgoingChat;
use App\Jobs\UpdateMessageStatus;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Endpoint POST /webhook/balesotomatis/{token}: keamanan, idempotensi, dan antrean.
 */
class WebhookTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
        Queue::fake();
    }

    public function test_wrong_token_is_404(): void
    {
        $this->postWebhook($this->incomingPayload(), 'salah')->assertNotFound();
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_webhook_disabled_when_token_not_configured(): void
    {
        config(['balesotomatis.webhook_token' => '']);

        $this->postWebhook($this->incomingPayload(), '')->assertNotFound();
        $this->postWebhook($this->incomingPayload(), 'apa-saja')->assertNotFound();
    }

    public function test_no_csrf_or_session_needed(): void
    {
        $this->post('/webhook/balesotomatis/'.self::WEBHOOK_TOKEN, $this->incomingPayload())->assertOk();
    }

    public function test_invalid_payload_is_422(): void
    {
        $this->postWebhook(['type' => 'incoming_chat'])->assertStatus(422);
        $this->postWebhook(['data' => []])->assertStatus(422);
        $this->postWebhook(['type' => 'incoming_chat', 'data' => 'teks'])->assertStatus(422);
    }

    public function test_unknown_device_is_rejected(): void
    {
        $this->postWebhook($this->incomingPayload(['number_key' => 'LAIN']))->assertForbidden();
        $this->postWebhook($this->incomingPayload(['number_key' => 'LAIN']), headers: ['BLS-OTO-NUMBERID' => 'LAIN'])->assertForbidden();
    }

    public function test_device_from_header_is_accepted(): void
    {
        $payload = $this->incomingPayload();
        unset($payload['data']['number_key']);

        $this->postWebhook($payload, headers: ['BLS-OTO-NUMBERID' => self::NUMBER_ID])->assertOk()->assertSee('ok');
        $this->assertSame(self::NUMBER_ID, WebhookEvent::sole()->number_key);
    }

    public function test_any_device_accepted_when_no_device_key_configured(): void
    {
        config(['balesotomatis.number_id' => null]);

        $this->postWebhook($this->incomingPayload(['number_key' => 'APA-SAJA']))->assertOk();
    }

    public function test_group_messages_are_ignored(): void
    {
        $this->postWebhook($this->incomingPayload(['is_group' => true]))->assertOk()->assertSee('ignored');
        $this->postWebhook($this->incomingPayload(['chat_id' => '1203630@g.us', 'is_group' => false]))->assertSee('ignored');

        $this->assertSame(0, WebhookEvent::count());
        Queue::assertNothingPushed();
    }

    public function test_incoming_outgoing_and_status_are_dispatched_to_their_jobs(): void
    {
        $this->postWebhook($this->incomingPayload())->assertOk();
        $this->postWebhook($this->incomingPayload([], 'outgoing_chat'))->assertOk();
        $this->postWebhook($this->incomingPayload(['status' => 'read'], 'message_status'))->assertOk();

        Queue::assertPushed(ProcessIncomingChat::class, 1);
        Queue::assertPushed(ProcessOutgoingChat::class, 1);
        Queue::assertPushed(UpdateMessageStatus::class, 1);

        $event = WebhookEvent::where('type', 'incoming_chat')->sole();
        $this->assertSame('bo.omni.event.v1', $event->payload['spec']);
        $this->assertNull($event->processed_at);
    }

    public function test_duplicate_message_id_is_stored_once(): void
    {
        $payload = $this->incomingPayload(['message_id' => 'DUP-1']);

        $this->postWebhook($payload)->assertSee('ok');
        $this->postWebhook($payload)->assertOk()->assertSee('duplicate');

        $this->assertSame(1, WebhookEvent::count());
        Queue::assertPushed(ProcessIncomingChat::class, 1);
    }

    public function test_same_message_id_with_new_status_is_not_a_duplicate(): void
    {
        foreach (['sent', 'delivered', 'read', 'read'] as $status) {
            $this->postWebhook($this->incomingPayload(['message_id' => 'wamid.X', 'status' => $status], 'message_status'));
        }

        $this->assertSame(3, WebhookEvent::count());
    }

    public function test_events_without_message_id_are_always_stored(): void
    {
        $payload = $this->incomingPayload(['message_id' => null]);

        $this->postWebhook($payload);
        $this->postWebhook($payload);

        $this->assertSame(2, WebhookEvent::count());
    }

    public function test_unknown_event_type_is_stored_but_not_processed(): void
    {
        $this->postWebhook($this->incomingPayload([], 'device_status'))->assertOk();

        $event = WebhookEvent::sole();
        $this->assertNotNull($event->processed_at);
        $this->assertSame('Tipe event tidak ditangani.', $event->error);
        Queue::assertNothingPushed();
    }

    public function test_urls_use_https_behind_tunnel_proxy(): void
    {
        $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'kancil.ngrok-free.app'])
            ->assertOk()
            ->assertSee('https://kancil.ngrok-free.app/login', false);
    }
}
