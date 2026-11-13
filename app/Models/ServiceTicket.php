<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tiket layanan petugas (antrean FIFO, LYN-YYYYMMDD-NNN).
 */
class ServiceTicket extends Model
{
    public const WAITING = 'Menunggu';

    public const HANDLING = 'Ditangani';

    public const RESOLVED = 'Selesai';

    public const EXPIRED = 'Kedaluwarsa';

    public const CANCELLED = 'Batal';

    public const OPEN = [self::WAITING, self::HANDLING];

    public const STATUSES = [self::WAITING, self::HANDLING, self::RESOLVED, self::EXPIRED, self::CANCELLED];

    protected $fillable = [
        'ticket_number',
        'queue_date',
        'queue_no',
        'conversation_id',
        'escalation_log_id',
        'escalation_code',
        'priority',
        'menu_path',
        'question_text',
        'status',
        'assigned_to',
        'queued_at',
        'taken_at',
        'first_response_at',
        'resolved_at',
        'last_notice_at',
        'close_reason',
    ];

    protected $casts = [
        'queue_date' => 'date',
        'queue_no' => 'integer',
        'queued_at' => 'datetime',
        'taken_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_notice_at' => 'datetime',
    ];

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN);
    }

    /** Urutan antrean: yang paling dulu masuk dilayani lebih dulu. */
    public function scopeFifo(Builder $query): Builder
    {
        return $query->orderBy('queued_at')->orderBy('id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function escalationLog(): BelongsTo
    {
        return $this->belongsTo(EscalationLog::class, 'escalation_log_id');
    }
}
