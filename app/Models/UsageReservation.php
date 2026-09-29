<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageReservation extends Model
{
    protected $fillable = ['monthly_feature_usage_id', 'token', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function monthlyFeatureUsage(): BelongsTo
    {
        return $this->belongsTo(MonthlyFeatureUsage::class);
    }
}
