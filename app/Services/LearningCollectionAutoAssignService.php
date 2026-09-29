<?php

namespace App\Services;

use App\Models\LearningCollection;
use App\Models\LearningSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LearningCollectionAutoAssignService
{
    /** @var array<string, array{name: string, description: string}> */
    private const COLLECTION_DEFAULTS = [
        'linuc' => ['name' => 'LinuC', 'description' => 'Linux・サーバー運用の学びをまとめています。'],
        'web' => ['name' => 'Web開発', 'description' => 'Laravel・PHP・Web開発の学びをまとめています。'],
        'cloud' => ['name' => 'クラウド / AWS', 'description' => 'AWS・クラウド基盤の学びをまとめています。'],
        'database' => ['name' => 'データベース', 'description' => 'SQL・データベースの学びをまとめています。'],
    ];

    /** @var array<string, string> */
    private const LEGACY_RULES_BY_SUBJECT = [
        'linuc' => 'linuc',
        'linux' => 'linuc',
        'linuc / linux' => 'linuc',
        'linux / linuc' => 'linuc',
        'web開発' => 'web',
        'web 開発' => 'web',
        'web development' => 'web',
        'web' => 'web',
        'クラウド' => 'cloud',
        'cloud' => 'cloud',
        'aws' => 'cloud',
        'クラウド / aws' => 'cloud',
        'aws / クラウド' => 'cloud',
        'データベース' => 'database',
        'database' => 'database',
    ];

    public function __construct(
        private LearningTopicClassifier $classifier,
        private DictionaryNormalizer $normalizer,
    ) {}

    public function assign(LearningSet $learningSet): bool
    {
        if (! $learningSet->questions()->exists()) {
            return false;
        }

        $target = $this->targetCollection($learningSet);

        return DB::transaction(function () use ($learningSet, $target): bool {
            if ($target === null) {
                return $this->detachWrongAutomaticAssignments($learningSet, null);
            }

            $collection = LearningCollection::withTrashed()
                ->whereBelongsTo($learningSet->user)
                ->where('auto_rule', $target['rule'])
                ->lockForUpdate()
                ->first();

            if ($collection?->trashed()) {
                return false;
            }

            $collection ??= $learningSet->user->learningCollections()->create([
                'name' => $target['name'],
                'description' => $target['description'],
                'auto_rule' => $target['rule'],
            ]);

            $changed = $this->detachWrongAutomaticAssignments($learningSet, $collection->id);
            $existingAssignment = DB::table('learning_collection_learning_set')
                ->where('learning_collection_id', $collection->id)
                ->where('learning_set_id', $learningSet->id)
                ->first(['assigned_by']);

            if ($existingAssignment !== null) {
                return $changed;
            }

            $collection->learningSets()->attach($learningSet->id, ['assigned_by' => 'auto']);

            return true;
        });
    }

    public function organize(User $user): int
    {
        $assignedCount = 0;

        $user->learningSets()
            ->whereHas('questions')
            ->with('user')
            ->chunkById(100, function ($learningSets) use (&$assignedCount): void {
                foreach ($learningSets as $learningSet) {
                    $assignedCount += $this->assign($learningSet) ? 1 : 0;
                }
            });

        return $assignedCount;
    }

    /** @return array{rule: string, name: string, description: string}|null */
    private function targetCollection(LearningSet $learningSet): ?array
    {
        $subject = mb_substr($this->normalizer->display((string) $learningSet->subject), 0, 100);
        if ($subject !== '') {
            $subjectKey = $this->normalizer->key($subject);
            $legacyRule = self::LEGACY_RULES_BY_SUBJECT[$subjectKey] ?? null;

            if ($legacyRule !== null) {
                return ['rule' => $legacyRule, ...self::COLLECTION_DEFAULTS[$legacyRule]];
            }

            return [
                'rule' => $this->subjectRule($subjectKey),
                'name' => $subject,
                'description' => $subject.'の学びをまとめています。',
            ];
        }

        $legacyRule = $this->classifier->autoRule(trim(($learningSet->topic ?? '').' '.$learningSet->title));

        return $legacyRule === null
            ? null
            : ['rule' => $legacyRule, ...self::COLLECTION_DEFAULTS[$legacyRule]];
    }

    private function subjectRule(string $subjectKey): string
    {
        return mb_strlen($subjectKey) <= 22
            ? 'subject:'.$subjectKey
            : 'subject:'.substr(hash('sha256', $subjectKey), 0, 22);
    }

    private function detachWrongAutomaticAssignments(LearningSet $learningSet, ?int $correctCollectionId): bool
    {
        $query = DB::table('learning_collection_learning_set')
            ->where('learning_set_id', $learningSet->id)
            ->where('assigned_by', 'auto');

        if ($correctCollectionId !== null) {
            $query->where('learning_collection_id', '!=', $correctCollectionId);
        }

        return $query->delete() > 0;
    }
}
