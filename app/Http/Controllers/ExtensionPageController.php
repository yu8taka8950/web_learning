<?php

namespace App\Http\Controllers;

use App\ExtensionAccessTokenAuthenticator;
use App\Models\AiUsageLog;
use App\Services\UsageLimitReachedException;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class ExtensionPageController extends Controller
{
    public function analyze(Request $request, ExtensionAccessTokenAuthenticator $authenticator, UsageLimitService $usageLimits): JsonResponse
    {
        $accessToken = $authenticator->authenticate($request);

        if ($accessToken === null) {
            return response()->json([
                'message' => '認証に失敗しました。',
            ], 401);
        }

        /*
         |--------------------------------------------------------------------------
         | Chrome拡張から送られたページ情報
         |--------------------------------------------------------------------------
         */

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'url' => ['required', 'url', 'max:5000'],
            'content' => ['required', 'string', 'max:20000'],
        ]);

        /*
         |--------------------------------------------------------------------------
         | Gemini設定
         |--------------------------------------------------------------------------
         */

        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model');

        if (! $apiKey) {
            return response()->json([
                'message' => 'Gemini APIキーが設定されていません。',
            ], 500);
        }

        try {
            $reservation = $usageLimits->reserve($accessToken->user, UsageLimitService::Web);
        } catch (UsageLimitReachedException $exception) {
            return response()->json(array_merge(['code' => 'usage_limit_reached'], $exception->usage, ['upgrade_url' => route('pricing')]), 429);
        }

        try {
            /*
             |--------------------------------------------------------------------------
             | Geminiへ渡す指示
             |--------------------------------------------------------------------------
             */

            $prompt = <<<PROMPT
あなたは学習教材作成アシスタントです。

以下のWebページを読んで、
このページを理解するために重要な専門用語を抽出してください。
                
【ルール】
・重要な専門用語を3〜6件程度選ぶ
・一般的すぎる単語は除外する
・広告、メニュー、ナビゲーションなどは除外する
・初心者が覚える価値のある用語を優先する
・Webページ本文の内容に基づいて選ぶ
・descriptionは初心者向けに短く分かりやすくする
・日本語で説明する
                
ページタイトル：
{$validated['title']}

URL：
{$validated['url']}

ページ本文：
{$validated['content']}
PROMPT;

            /*
             |--------------------------------------------------------------------------
             | Gemini APIへ送信
             |--------------------------------------------------------------------------
             */

            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->acceptJson()
                ->timeout(60)
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    [
                                        'text' => $prompt,
                                    ],
                                ],
                            ],
                        ],

                        /*
                     * JSON形式で返してもらう
                     */
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 1500,
                            'responseMimeType' => 'application/json',
                            'responseSchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'terms' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'term' => [
                                                    'type' => 'string',
                                                ],
                                                'description' => [
                                                    'type' => 'string',
                                                ],
                                            ],
                                            'required' => [
                                                'term',
                                                'description',
                                            ],
                                        ],
                                    ],
                                ],
                                'required' => [
                                    'terms',
                                ],
                            ],
                        ],
                    ]
                );

            /*
             |--------------------------------------------------------------------------
             | Gemini API利用量を取得
             |--------------------------------------------------------------------------
             |
             | Geminiから返された usageMetadata を使って、
             | 入力・出力・合計トークン数をDBへ保存します。
             |
             */

            $usage = $response->json('usageMetadata', []);

            /*
             |--------------------------------------------------------------------------
             | Gemini側でエラー
             |--------------------------------------------------------------------------
             */

            if ($response->failed()) {
                $this->recordAiUsage([
                    'user_id' => $accessToken->user_id,
                    'provider' => 'gemini',
                    'model' => $model,
                    'feature' => 'web_term_detection',
                    'input_characters' => mb_strlen($validated['content']),
                    'input_tokens' => $usage['promptTokenCount'] ?? null,
                    'output_tokens' => $usage['candidatesTokenCount'] ?? null,
                    'total_tokens' => $usage['totalTokenCount'] ?? null,
                    'success' => false,
                    'http_status' => $response->status(),
                    'error_message' => mb_substr(
                        $response->body(),
                        0,
                        5000
                    ),
                ]);

                $usageLimits->release($reservation['token']);

                return response()->json([
                    'message' => 'Geminiによる解析に失敗しました。',
                    'status' => $response->status(),

                    /*
                     * 開発中だけ確認用
                     */
                    'gemini_error' => $response->json(),
                ], 502);
            }

            /*
             |--------------------------------------------------------------------------
             | Geminiの回答を取得
             |--------------------------------------------------------------------------
             */

            $outputText = $response->json(
                'candidates.0.content.parts.0.text'
            );

            if (! $outputText) {
                return response()->json([
                    'message' => 'Geminiの回答を取得できませんでした。',
                ], 502);
            }

            /*
             |--------------------------------------------------------------------------
             | JSON文字列をPHP配列へ変換
             |--------------------------------------------------------------------------
             */

            $aiData = json_decode(
                $outputText,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $this->recordAiUsage([
                'user_id' => $accessToken->user_id,
                'provider' => 'gemini', 'model' => $model, 'feature' => 'web_term_detection',
                'input_characters' => mb_strlen($validated['content']), 'input_tokens' => $usage['promptTokenCount'] ?? null,
                'output_tokens' => $usage['candidatesTokenCount'] ?? null, 'total_tokens' => $usage['totalTokenCount'] ?? null,
                'success' => true, 'http_status' => $response->status(), 'error_message' => null,
            ]);
            $usageLimits->complete($reservation['token']);

            /*
             |--------------------------------------------------------------------------
             | Chrome拡張へ返す
             |--------------------------------------------------------------------------
             */

            return response()->json([
                'page_title' => $validated['title'],
                'source_url' => $validated['url'],
                'terms' => $aiData['terms'] ?? [],
            ]);
        } catch (Throwable $e) {
            $usageLimits->release($reservation['token']);
            report($e);

            return response()->json([
                'message' => 'AI解析中にエラーが発生しました。',
            ], 500);
        }
    }

    /*
     |--------------------------------------------------------------------------
     | AI利用履歴を保存
     |--------------------------------------------------------------------------
     |
     | 利用履歴の保存自体に失敗しても、
     | 本来のGemini解析まで失敗扱いにしないようにしています。
     |
     */

    private function recordAiUsage(array $data): void
    {
        try {
            AiUsageLog::create($data);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
