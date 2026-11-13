<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    protected $table = 'complaints';

    protected $fillable = [
        'ticket_number',
        'conversation_id',
        'category_id',
        'user_name',
        'whatsapp_number',
        'complaint_text',
        'status',
        'assigned_to',
        'response_text',
        'submitted_at',
        'resolved_at',
        'priority',
        'escalation_level',
        'attachments',
        'first_response_at',
    ];

    /** Nama status untuk pemohon (Dokumen Skenario memakai "Baru"). */
    public const STATUS_LABEL_FOR_USER = [
        'Menunggu' => 'Baru',
        'Diproses' => 'Diproses',
        'Selesai' => 'Selesai',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'resolved_at' => 'datetime',
        'first_response_at' => 'datetime',
        'attachments' => 'array',
        'escalation_level' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'category_id');
    }

    public function assignedPetugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
