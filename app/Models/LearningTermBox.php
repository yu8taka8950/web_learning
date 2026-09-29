<?php

namespace App\Models;

use Database\Factories\LearningTermBoxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'name_key', 'kind', 'auto_rule'])]
class LearningTermBox extends Model
{
    public const MAX_NAME_LENGTH = 80;

    public const KIND_AUTO = 'auto';

    public const KIND_MANUAL = 'manual';

    /** @use HasFactory<LearningTermBoxFactory> */
    use HasFactory, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function learningTerms(): BelongsToMany
    {
        return $this->belongsToMany(LearningTerm::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
