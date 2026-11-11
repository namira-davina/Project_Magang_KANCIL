<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'llm_logs';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'model',
        'question',
        'answer',
        'faq_codes',
        'confident',
        'prompt_tokens',
        'completion_tokens',
        'latency_ms',
        'error',
    ];

    protected $casts = [
        'faq_codes' => 'array',
        'confident' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
