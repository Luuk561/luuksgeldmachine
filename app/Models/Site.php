<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = [
        'domain',
        'name',
        'fathom_site_id',
        'niche',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function metricsPages(): HasMany
    {
        return $this->hasMany(MetricPage::class);
    }
}
