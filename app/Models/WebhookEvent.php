<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $table = 'webhook_events';

    protected $fillable = [
        'type',
        'number_key',
        'message_id',
        'dedupe_key',
        'payload',
        'processed_at',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    /** @return array<string, mixed> */
    public function data(): array
    {
        return (array) ($this->payload['data'] ?? []);
    }
}
