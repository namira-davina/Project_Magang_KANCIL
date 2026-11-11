<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalationLog extends Model
{
    // Table has created_at only (see kancil_sapa.sql).
    public const UPDATED_AT = null;

    protected $table = 'escalation_logs';

    protected $fillable = [
        'conversation_id',
        'handled_by',
        'escalation_type',
        'reason',
        'status',
        'escalated_at',
        'taken_at',
        'completed_at',
    ];

    protected $casts = [
        'escalated_at' => 'datetime',
        'taken_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
