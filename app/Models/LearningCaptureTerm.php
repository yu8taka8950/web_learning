<?php

namespace App\Models;

use Database\Factories\LearningCaptureTermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningCaptureTerm extends Model
{
    /** @use HasFactory<LearningCaptureTermFactory> */
    use HasFactory;

    protected $fillable = ['term', 'normalized_term', 'explanation', 'sort_order'];

    public function capture(): BelongsTo
    {
        return $this->belongsTo(LearningCapture::class, 'learning_capture_id');
    }
}
