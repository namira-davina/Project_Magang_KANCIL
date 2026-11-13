<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'escalation_code',
        'faq_id',
        'priority',
        'menu_path',
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

    /** Eskalasi sebenarnya: B0 (pengaduan terputus) tidak dihitung. */
    public function scopeEscalations(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('escalation_code')->orWhere('escalation_code', '!=', 'B0'));
    }

    /** Log yang ikut status percakapan (ambil alih/kembali ke bot). B1 mengikuti status tiket pengaduan. */
    public function scopeChatHandoff(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('escalation_code')->orWhere('escalation_code', '!=', 'B1'));
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class, 'faq_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
