<?php

namespace App\Services;

use App\Models\LearningSet;
use App\Models\LearningTerm;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LearningTermSaveService
{
    public const MAX_TERM_LENGTH = 100;

    public const MAX_DESCRIPTION_LENGTH = 500;

    public function __construct(private DictionaryNormalizer $normalizer) {}

    public function normalizeTerm(string $term): string
    {
        return $this->normalizer->display($term);
    }

    public function sourceText(Question $question, string $sourceField): ?string
    {
        $text = match ($sourceField) {
            'question' => $question->question,
            'input_question' => $question->input_question ?: $question->question,
            'explanation' => $question->explanation,
            default => null,
        };

        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    public function containsSelectedTerm(string $sourceText, string $term): bool
    {
        return str_contains($this->normalizeTerm($sourceText), $term);
    }

    public function descriptionFor(string $sourceText, string $term): string
    {
        $sentences = preg_split('/(?<=[。！？.!?])|\R+/u', trim($sourceText), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence !== '' && $this->containsSelectedTerm($sentence, $term)) {
                return $this->limitDescription($sentence);
            }
        }

        return $this->limitDescription(trim($sourceText));
    }

    /**
     * @return array{created: bool, term: string}
     */
    public function save(User $user, Question $question, string $sourceField, string $selectedText, ?string $description = null): array
    {
        $term = $this->normalizeTerm($selectedText);
        $sourceText = $this->sourceText($question, $sourceField);
        $description = is_string($description) && trim($description) !== ''
            ? $this->limitDescription(trim($description))
            : ($sourceText === null ? '' : $this->descriptionFor($sourceText, $term));
        $lock = Cache::lock('learning-term-save:'.$user->id.':'.hash('sha256', mb_strtolower($term)), 5);

        $created = $lock->block(1, function () use ($user, $question, $term, $description): bool {
            return DB::transaction(function () use ($user, $question, $term, $description): bool {
                if ($this->hasActiveDuplicate($user, $term)) {
                    return false;
                }

                $deletedTerm = $question->learningSet->learningTerms()
                    ->onlyTrashed()
                    ->get(['id', 'term'])
                    ->first(fn (LearningTerm $learningTerm): bool => $this->normalizer->key($learningTerm->term) === $this->normalizer->key($term));

                if ($deletedTerm !== null) {
                    $deletedTerm->restore();
                    $deletedTerm->update([
                        'term' => $term,
                        'description' => $description,
                        'source_type' => $question->learningSet->source_type,
                        'source_url' => $question->learningSet->source_url,
                    ]);

                    return true;
                }

                $question->learningSet->learningTerms()->create([
                    'term' => $term,
                    'description' => $description,
                    'source_type' => $question->learningSet->source_type,
                    'source_url' => $question->learningSet->source_url,
                ]);

                return true;
            });
        });

        return ['created' => $created, 'term' => $term];
    }

    public function hasActiveDuplicate(User $user, string $term, ?int $exceptTermId = null): bool
    {
        return LearningTerm::query()
            ->whereHas('learningSet', fn (Builder $query) => $query->whereBelongsTo($user))
            ->when($exceptTermId !== null, fn (Builder $query) => $query->whereKeyNot($exceptTermId))
            ->select(['id', 'term'])
            ->cursor()
            ->contains(fn (LearningTerm $learningTerm): bool => $this->normalizer->key($learningTerm->term) === $this->normalizer->key($term));
    }

    public function hasDuplicateInLearningSetIncludingDeleted(LearningSet $learningSet, string $term, int $exceptTermId): bool
    {
        return $learningSet->learningTerms()
            ->withTrashed()
            ->whereKeyNot($exceptTermId)
            ->get(['id', 'term'])
            ->contains(fn (LearningTerm $learningTerm): bool => $this->normalizer->key($learningTerm->term) === $this->normalizer->key($term));
    }

    private function limitDescription(string $description): string
    {
        return mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH);
    }
}
