<?php

namespace App\Models;

use Database\Factories\LearningCaptureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningCapture extends Model
{
    /** @use HasFactory<LearningCaptureFactory> */
    use HasFactory;

    protected $fillable = ['page_title', 'source_url', 'normalized_source_url', 'source_host', 'status', 'captured_at', 'last_analyzed_at'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'last_analyzed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(LearningCaptureTerm::class);
    }
}
