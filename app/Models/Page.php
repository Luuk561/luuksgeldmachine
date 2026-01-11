<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $fillable = [
        'site_id',
        'url',
        'pathname',
        'content_type',
        'title',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
