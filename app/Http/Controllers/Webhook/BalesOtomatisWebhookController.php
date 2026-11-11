<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingChat;
use App\Jobs\ProcessOutgoingChat;
use App\Jobs\UpdateMessageStatus;
use App\Models\WebhookEvent;
use App\Services\BalesOtomatis\BalesOtomatisClient;
use App\Services\BalesOtomatis\WebhookData;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /webhook/balesotomatis/{token}
 *
 * BalesOtomatis tidak memakai tanda tangan dan tidak mengirim ulang, jadi:
 * token acak di URL, cocokkan number_key, simpan mentah (idempoten), proses di antrean, balas 200 cepat.
 */
class BalesOtomatisWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, BalesOtomatisClient $client): Response
    {
        $expected = (string) config('balesotomatis.webhook_token');

        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        $type = $request->input('type');
        $payload = $request->input('data');

        if (! is_string($type) || ! is_array($payload)) {
            return response('invalid payload', 422);
        }

        $data = new WebhookData($payload);
        $key = $request->header('BLS-OTO-NUMBERID') ?: $data->numberKey();
        $allowed = $client->deviceKeys();

        if ($allowed !== [] && ! in_array((string) $key, $allowed, true)) {
            return response('unknown device', 403);
        }

        if ($data->isGroup()) {
            return response('ignored', 200);
        }

        $dedupe = $data->messageId()
            ? hash('sha256', $type.'|'.$data->messageId().'|'.($type === 'message_status' ? (string) $data->deliveryStatus() : ''))
            : null;

        if ($dedupe && WebhookEvent::where('dedupe_key', $dedupe)->exists()) {
            return response('duplicate', 200);
        }

        try {
            $event = WebhookEvent::create([
                'type' => mb_substr($type, 0, 40),
                'number_key' => $key ? mb_substr((string) $key, 0, 100) : null,
                'message_id' => $data->messageId(),
                'dedupe_key' => $dedupe,
                'payload' => $request->all(),
            ]);
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                return response('duplicate', 200);
            }

            throw $e;
        }

        match ($type) {
            'incoming_chat' => ProcessIncomingChat::dispatch($event->id),
            'outgoing_chat' => ProcessOutgoingChat::dispatch($event->id),
            'message_status' => UpdateMessageStatus::dispatch($event->id),
            default => $event->update(['processed_at' => now(), 'error' => 'Tipe event tidak ditangani.']),
        };

        return response('ok', 200);
    }
}
