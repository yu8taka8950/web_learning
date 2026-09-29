<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['term', 'description', 'source_type', 'source_url'])]
class LearningTerm extends Model
{
    use SoftDeletes;

    public function learningSet(): BelongsTo
    {
        return $this->belongsTo(LearningSet::class);
    }

    public function learningSetIncludingDeleted(): BelongsTo
    {
        return $this->belongsTo(LearningSet::class, 'learning_set_id')->withTrashed();
    }

    public function boxes(): BelongsToMany
    {
        return $this->belongsToMany(LearningTermBox::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
