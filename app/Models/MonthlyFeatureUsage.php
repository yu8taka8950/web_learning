<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyFeatureUsage extends Model
{
    protected $fillable = ['user_id', 'feature', 'period_starts_at'];

    protected function casts(): array
    {
        return ['period_starts_at' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(UsageReservation::class);
    }
}
