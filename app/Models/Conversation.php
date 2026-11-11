<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $table = 'conversations';

    public const MODE_BOT = 'BOT_ACTIVE';

    public const MODE_WAITING = 'WAITING_HUMAN';

    public const MODE_HUMAN = 'HUMAN_HANDLING';

    public const MODE_CLOSED = 'CLOSED';

    protected $fillable = [
        'wa_chat_id',
        'device_key',
        'session_state',
        'last_inbound_at',
        'bot_paused_until',
        'ticket_number',
        'whatsapp_number',
        'user_name',
        'channel',
        'conversation_mode',
        'current_menu',
        'assigned_to',
        'started_at',
        'last_message_at',
        'closed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'bot_paused_until' => 'datetime',
        'session_state' => 'array',
    ];

    /** Kode tampilan di dashboard, mis. KS-00012. */
    public function displayCode(): string
    {
        return $this->ticket_number ?: 'KS-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /** Petugas sedang menangani: bot tidak boleh membalas. */
    public function isHumanHandling(): bool
    {
        return $this->conversation_mode === self::MODE_HUMAN
            && ($this->bot_paused_until === null || $this->bot_paused_until->isFuture());
    }

    /** Nomor tujuan kirim (tanpa +), atau null untuk kontak LID tanpa nomor. */
    public function recipientPhone(): ?string
    {
        if ($this->whatsapp_number) {
            return $this->whatsapp_number;
        }

        if ($this->wa_chat_id && str_ends_with($this->wa_chat_id, '@s.whatsapp.net')) {
            return strstr($this->wa_chat_id, '@', true);
        }

        return null;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function complaint(): HasOne
    {
        return $this->hasOne(Complaint::class, 'conversation_id');
    }

    public function assignedPetugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function escalationLogs(): HasMany
    {
        return $this->hasMany(EscalationLog::class, 'conversation_id');
    }
}
