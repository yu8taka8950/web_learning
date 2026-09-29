<?php

namespace App\Models;

use Database\Factories\TermExplanationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'normalized_term',
    'display_term',
    'subject_key',
    'subject_label',
    'topic_label',
    'explanation',
    'provider',
    'model',
])]
class TermExplanation extends Model
{
    /** @use HasFactory<TermExplanationFactory> */
    use HasFactory;
}
