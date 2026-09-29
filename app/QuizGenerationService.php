<?php

namespace App;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use JsonException;
use Throwable;

class QuizGenerationService
{
    /**
     * @param  array<int, array{term: string, description: string}>  $terms
     * @return array{subject: string, topic: string, questions: array<int, array<string, string>>}
     */
    public function generate(string $title, ?string $sourceUrl, array $terms, ?int $userId = null): array
    {
        $apiKey = config('services.gemini.key');
        $model = (string) config('services.gemini.model');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new QuizGenerationException('Gemini APIキーが設定されていません。', 500);
        }

        $prompt = $this->prompt($title, $sourceUrl, $terms);
        $inputCharacters = mb_strlen($prompt);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->acceptJson()->connectTimeout(10)->timeout(60)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 12000,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $this->responseSchema(),
                    ],
                ]);

            $usage = $response->json('usageMetadata', []);

            if ($response->failed()) {
                $this->recordUsage($model, $inputCharacters, $usage, false, $response->status(), mb_substr($response->body(), 0, 5000), $userId);
                throw new QuizGenerationException('Geminiによる問題生成に失敗しました。', 502);
            }

            $outputText = $response->json('candidates.0.content.parts.0.text');

            if (! is_string($outputText) || $outputText === '') {
                $this->recordUsage($model, $inputCharacters, $usage, false, $response->status(), 'Geminiの回答を取得できませんでした。', $userId);
                throw new QuizGenerationException('Geminiの回答を取得できませんでした。', 502);
            }

            try {
                $data = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $this->recordUsage($model, $inputCharacters, $usage, false, $response->status(), $exception->getMessage(), $userId);
                throw new QuizGenerationException('Geminiの問題データを読み取れませんでした。', 502, $exception);
            }

            $validator = Validator::make($data, $this->rules());

            if ($validator->fails()) {
                $this->recordUsage($model, $inputCharacters, $usage, false, $response->status(), $validator->errors()->first(), $userId);
                throw new QuizGenerationException('Geminiの問題データが不正です。', 502);
            }

            $this->recordUsage($model, $inputCharacters, $usage, true, $response->status(), null, $userId);

            return $validator->validated();
        } catch (QuizGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->recordUsage($model, $inputCharacters, [], false, null, mb_substr($exception->getMessage(), 0, 5000), $userId);
            throw new QuizGenerationException('AI問題生成中にエラーが発生しました。', 500, $exception);
        }
    }

    /** @param array<int, array{term: string, description: string}> $terms */
    private function prompt(string $title, ?string $sourceUrl, array $terms): string
    {
        $encodedTerms = json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $source = $sourceUrl ? "URL：\n{$sourceUrl}" : '出所：アップロードされたスクリーンショット';

        return <<<PROMPT
あなたは学習教材作成アシスタントです。

以下の資料情報と選択済みの学習候補だけを根拠に、初心者向けの4択問題を作成してください。

【ルール】
・選択された候補1件につき原則1問、選択数と同じ数の問題を作る
・問題文は、その候補を理解できているか確認できる内容にする
・選択肢はA/B/C/Dの4つを必ず用意する
・correct_optionはA、B、C、Dのいずれか1つにする
・input_questionは選択肢を一切見ずに、correct_optionの本文を1つの短い答えとして入力できる問題文にする
・input_questionでは「次のうち」「どれか」「選びなさい」「最も適切なものは」など、選択肢を参照する表現を使わない
・input_questionは4択版の意味を変えすぎず、正解そのものや余計なヒントを含めない、1〜2文の自然で簡潔な日本語にする
・誤答はもっともらしいが、明確に誤りと分かる内容にする
・問題文だけで正解が露骨に分からないようにする
・explanationは、なぜその答えが正解なのか、重要な概念、分野に合った具体例1つが分かる初心者向けの自然な日本語にする
・explanationは2〜4文、150〜350文字程度を目安にし、単純な内容を無理に水増ししない
・explanationは問題文をそのまま繰り返したり、正解だけを述べて終えたりしない
・数学では可能なら簡単な数式・数値例、英語文法では短い英文例など、その分野で理解しやすい例を選ぶ
・理解に役立つ場合のみ、誤答しやすい選択肢との違いを1文で簡潔に補足してよい
・explanationには不要な前置き、Markdown見出し、架空のURLを含めない
・選択されていない候補の問題を追加しない
・subjectは、この教材が属する広い学習分野を一般的・代表的な短い名称で返す
・subjectは記事タイトルをそのまま使わず、「宅地建物取引士試験」は「宅建」、「日商簿記」は「簿記」、「Linux資格LinuC」は「LinuC」のように表記揺れを抑える
・subjectは固定カテゴリから選ぶ必要はなく、数学、英語、日本史、物理、看護、法律など内容に合う分野名を自然に付ける
・topicは用語群の学習内容が初心者にも分かる、10〜30文字程度の簡潔な日本語にする

資料タイトル：
{$title}

{$source}

選択済み候補（JSON）：
{$encodedTerms}
PROMPT;
    }

    /** @return array<string, mixed> */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'subject' => ['type' => 'string'],
                'topic' => ['type' => 'string'],
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'term' => ['type' => 'string'],
                            'question' => ['type' => 'string'],
                            'input_question' => ['type' => 'string'],
                            'option_a' => ['type' => 'string'],
                            'option_b' => ['type' => 'string'],
                            'option_c' => ['type' => 'string'],
                            'option_d' => ['type' => 'string'],
                            'correct_option' => ['type' => 'string', 'enum' => ['A', 'B', 'C', 'D']],
                            'explanation' => ['type' => 'string'],
                        ],
                        'required' => ['term', 'question', 'input_question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation'],
                    ],
                ],
            ],
            'required' => ['subject', 'topic', 'questions'],
        ];
    }

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:1', 'max:100'],
            'topic' => ['required', 'string', 'min:1', 'max:100'],
            'questions' => ['required', 'array', 'min:1', 'max:20'],
            'questions.*.term' => ['required', 'string', 'max:200'],
            'questions.*.question' => ['required', 'string'],
            'questions.*.input_question' => ['required', 'string'],
            'questions.*.option_a' => ['required', 'string'], 'questions.*.option_b' => ['required', 'string'],
            'questions.*.option_c' => ['required', 'string'], 'questions.*.option_d' => ['required', 'string'],
            'questions.*.correct_option' => ['required', 'in:A,B,C,D'], 'questions.*.explanation' => ['required', 'string'],
        ];
    }

    /** @param array<string, mixed> $usage */
    private function recordUsage(string $model, int $inputCharacters, array $usage, bool $success, ?int $httpStatus, ?string $errorMessage = null, ?int $userId = null): void
    {
        try {
            AiUsageLog::query()->create([
                'user_id' => $userId, 'provider' => 'gemini', 'model' => $model, 'feature' => 'quiz_generation',
                'input_characters' => $inputCharacters, 'input_tokens' => $usage['promptTokenCount'] ?? null,
                'output_tokens' => $usage['candidatesTokenCount'] ?? null, 'total_tokens' => $usage['totalTokenCount'] ?? null,
                'success' => $success, 'http_status' => $httpStatus, 'error_message' => $errorMessage,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
