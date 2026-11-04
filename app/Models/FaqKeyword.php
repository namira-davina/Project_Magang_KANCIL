<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqKeyword extends Model
{
    protected $table = 'faq_keywords';

    protected $fillable = [
        'faq_id',
        'keyword',
    ];

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class, 'faq_id');
    }
}