<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $table = 'conversations';

    protected $fillable = [
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
    ];

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