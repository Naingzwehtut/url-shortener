<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrlClick extends Model
{
    public $timestamps = false; // accessed_at is the only timestamp we track here

    protected $fillable = [
        'url_id',
        'accessed_at',
        'user_agent',
        'referrer',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }
}
