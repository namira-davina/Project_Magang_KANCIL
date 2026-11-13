<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faq extends Model
{
    protected $table = 'faqs';

    protected $fillable = [
        'code',
        'menu_path',
        'category_id',
        'question',
        'answer',
        'source',
        'source_url',
        'verification_status',
        'verified_by',
        'verified_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** Boleh ditampilkan bot: terverifikasi dan aktif. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('verification_status', 'terverifikasi')->where('is_active', 1);
    }

    public function isPublished(): bool
    {
        return $this->verification_status === 'terverifikasi' && $this->is_active;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'category_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(FaqKeyword::class, 'faq_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
