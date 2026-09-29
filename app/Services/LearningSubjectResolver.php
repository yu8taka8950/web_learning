<?php

namespace App\Services;

use App\Models\LearningSet;

class LearningSubjectResolver
{
    public function __construct(
        private LearningTopicClassifier $topicClassifier,
        private DictionaryNormalizer $normalizer,
    ) {}

    /** @return array{key: string, label: string, topic: ?string} */
    public function resolve(LearningSet $learningSet): array
    {
        $subject = $this->normalizer->display((string) $learningSet->subject);

        if ($subject === '') {
            $subject = match ($this->topicClassifier->autoRule(trim((string) $learningSet->topic).' '.$learningSet->title)) {
                'linuc' => 'LinuC',
                'cloud' => 'クラウド',
                'database' => 'データベース',
                'web' => 'Web',
                default => '一般',
            };
        }

        $topic = $this->normalizer->display((string) $learningSet->topic);

        return [
            'key' => $this->normalizer->key($subject),
            'label' => mb_substr($subject, 0, 100),
            'topic' => $topic === '' ? null : mb_substr($topic, 0, 255),
        ];
    }
}
