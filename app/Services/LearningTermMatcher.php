<?php

namespace App\Services;

use App\Models\LearningSet;
use App\Models\LearningTerm;
use App\Models\TermExplanation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LearningTermMatcher
{
    public const MAX_INTERACTIVE_TERMS = 5;

    private const MAX_QUERY_MATCHES = 50;

    public function __construct(
        private DictionaryNormalizer $normalizer,
        private LearningSubjectResolver $subjectResolver,
        private TermExplanationFormatter $formatter,
    ) {}

    /**
     * @return array<int, array{type: 'text'|'term', text: string, term?: string, description?: string}>
     */
    public function segmentsFor(User $user, LearningSet $learningSet, ?string $text, array $excludedTerms = []): array
    {
        if (! is_string($text) || $text === '') {
            return [['type' => 'text', 'text' => '']];
        }

        $personalTerms = LearningTerm::query()
            ->whereHas('learningSet', fn (Builder $query) => $query->whereBelongsTo($user))
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->tap(fn (Builder $query) => $this->whereTermAppearsInText($query, 'term', $text))
            ->select(['id', 'learning_set_id', 'term', 'description', 'created_at'])
            ->latest('id')
            ->limit(self::MAX_QUERY_MATCHES)
            ->get()
            ->filter(fn (LearningTerm $term): bool => $this->isMatchCandidate($term->term, $term->description))
            ->unique(fn (LearningTerm $term): string => $this->normalizer->key($term->term))
            ->map(fn (LearningTerm $term): array => [
                'term' => $term->term,
                'description' => trim((string) $term->description),
                'personal' => true,
            ]);

        $subject = $this->subjectResolver->resolve($learningSet);
        $sharedTerms = TermExplanation::query()
            ->where('subject_key', $subject['key'])
            ->whereNotNull('explanation')
            ->where('explanation', '!=', '')
            ->tap(fn (Builder $query) => $this->whereTermAppearsInText($query, 'display_term', $text))
            ->select(['id', 'normalized_term', 'display_term', 'explanation'])
            ->limit(self::MAX_QUERY_MATCHES)
            ->get()
            ->filter(fn (TermExplanation $term): bool => $this->isMatchCandidate($term->display_term, $term->explanation))
            ->mapWithKeys(fn (TermExplanation $term): array => [
                $term->normalized_term => [
                    'term' => $term->display_term,
                    'description' => $this->formatter->format($term->display_term, $term->explanation),
                    'personal' => false,
                ],
            ]);

        foreach ($personalTerms as $personalTerm) {
            $key = $this->normalizer->key($personalTerm['term']);
            if ($sharedTerms->has($key)) {
                $sharedTerms[$key] = [...$sharedTerms[$key], 'personal' => true];

                continue;
            }

            $sharedTerms[$key] = $personalTerm;
        }

        $terms = $sharedTerms
            ->sort(function (array $first, array $second): int {
                $personalOrder = ((int) $second['personal']) <=> ((int) $first['personal']);

                return $personalOrder !== 0
                    ? $personalOrder
                    : mb_strlen($second['term']) <=> mb_strlen($first['term']);
            })
            ->take(self::MAX_INTERACTIVE_TERMS)
            ->values();

        return $this->segments($text, $terms, $excludedTerms);
    }

    /**
     * @param  Collection<int, LearningTerm|array{term: string, description: string, personal?: bool}>  $terms
     * @return array<int, array{type: 'text'|'term', text: string, term?: string, description?: string}>
     */
    public function segments(string $text, Collection $terms, array $excludedTerms = []): array
    {
        $excludedKeys = collect($excludedTerms)
            ->filter(fn (mixed $term): bool => is_string($term) && trim($term) !== '')
            ->mapWithKeys(fn (string $term): array => [$this->normalizer->key($term) => true]);
        $terms = $terms
            ->map(function (LearningTerm|array $term): array {
                if ($term instanceof LearningTerm) {
                    return ['term' => $term->term, 'description' => trim((string) $term->description)];
                }

                return ['term' => $term['term'], 'description' => trim($term['description'])];
            })
            ->filter(fn (array $term): bool => $this->isMatchCandidate($term['term'], $term['description']))
            ->reject(fn (array $term): bool => $excludedKeys->has($this->normalizer->key($term['term'])))
            ->sortByDesc(fn (array $term): int => mb_strlen($term['term']))
            ->values();

        if ($terms->isEmpty()) {
            return [['type' => 'text', 'text' => $text]];
        }

        $byMatch = $terms->keyBy(fn (array $term): string => $this->normalizer->key($term['term']));
        $alternatives = $terms->map(function (array $term): string {
            $escaped = preg_quote($term['term'], '/');

            return preg_match('/^[A-Za-z0-9_]+$/', $term['term']) === 1
                ? '(?<![A-Za-z0-9_])'.$escaped.'(?![A-Za-z0-9_])'
                : $escaped;
        })->implode('|');

        preg_match_all('/(?:'.$alternatives.')/iu', $text, $matches, PREG_OFFSET_CAPTURE);
        if ($matches[0] === []) {
            return [['type' => 'text', 'text' => $text]];
        }

        $segments = [];
        $offset = 0;
        $seenTerms = [];
        foreach ($matches[0] as [$matchedText, $byteOffset]) {
            $term = $byMatch->get($this->normalizer->key($matchedText));
            if (! is_array($term)) {
                continue;
            }

            $before = substr($text, $offset, $byteOffset - $offset);
            if ($before !== '') {
                $segments[] = ['type' => 'text', 'text' => $before];
            }
            $termKey = $this->normalizer->key($matchedText);
            if (isset($seenTerms[$termKey])) {
                $segments[] = ['type' => 'text', 'text' => $matchedText];
                $offset = $byteOffset + strlen($matchedText);

                continue;
            }

            $seenTerms[$termKey] = true;
            $segments[] = ['type' => 'term', 'text' => $matchedText, 'term' => $term['term'], 'description' => $term['description']];
            $offset = $byteOffset + strlen($matchedText);
        }

        $after = substr($text, $offset);
        if ($after !== '' || $segments === []) {
            $segments[] = ['type' => 'text', 'text' => $after];
        }

        return $segments;
    }

    private function whereTermAppearsInText(Builder $query, string $column, string $text): void
    {
        $driver = $query->getConnection()->getDriverName();
        $lengthFunction = $driver === 'sqlite' ? 'length' : 'char_length';

        $query->whereRaw(
            $driver === 'sqlite'
                ? "instr(lower(?), lower({$column})) > 0"
                : "locate(lower({$column}), lower(?)) > 0",
            [$text],
        )->whereRaw("{$lengthFunction}({$column}) >= 2")
            ->orderByRaw("{$lengthFunction}({$column}) desc");
    }

    private function isMatchCandidate(string $term, ?string $description): bool
    {
        return mb_strlen(trim($term)) >= 2 && trim((string) $description) !== '';
    }
}
