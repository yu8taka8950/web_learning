<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use App\Models\ExtensionQuizDraft;
use App\QuizGenerationException;
use App\QuizGenerationService;
use App\Services\UsageLimitReachedException;
use App\Services\UsageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JsonException;
use RuntimeException;
use Throwable;

class ScreenshotLearningController extends Controller
{
    public function create(Request $request, UsageLimitService $usageLimits): View
    {
        return view('screenshot-learning.create', ['usage' => $usageLimits->usage($request->user(), UsageLimitService::Screenshot)]);
    }

    public function analyze(Request $request, UsageLimitService $usageLimits): RedirectResponse
    {
        $validated = $request->validate([
            'screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $apiKey = config('services.gemini.key');

        if (! is_string($apiKey) || $apiKey === '') {
            return back()->withErrors(['screenshot' => 'Gemini APIキーが設定されていません。']);
        }

        try {
            $reservation = $usageLimits->reserve($request->user(), UsageLimitService::Screenshot);
        } catch (UsageLimitReachedException $exception) {
            return back()->with('usage-limit', $exception->usage);
        }

        $image = $validated['screenshot'];
        $path = $image->store('screenshots', 'local');

        if (! is_string($path)) {
            return back()->withErrors(['screenshot' => '画像を保存できませんでした。']);
        }

        try {
            $analysis = $this->analyzeImage(file_get_contents($image->getRealPath()), $image->getMimeType(), (string) config('services.gemini.model'), $apiKey, $request->user()->id);
            $draft = $request->user()->extensionQuizDrafts()->create([
                'token' => (string) Str::uuid(),
                'source_type' => 'screenshot',
                'source_title' => $analysis['title'],
                'source_url' => null,
                'source_image_path' => $path,
                'selected_terms' => $analysis['candidates'],
                'generated_questions' => [],
                'expires_at' => now()->addDay(),
            ]);
            $usageLimits->complete($reservation['token']);

            return redirect()->route('screenshot-learning.candidates', $draft->token);
        } catch (Throwable $exception) {
            $usageLimits->release($reservation['token']);
            Storage::disk('local')->delete($path);
            report($exception);

            return back()->withErrors(['screenshot' => 'スクリーンショットの解析に失敗しました。時間をおいて再度お試しください。']);
        }
    }

    public function candidates(Request $request, string $token): View
    {
        return view('screenshot-learning.candidates', ['draft' => $this->pendingDraft($request, $token)]);
    }

    public function generate(Request $request, string $token, QuizGenerationService $quizGenerationService): RedirectResponse
    {
        $validated = $request->validate([
            'candidates' => ['required', 'array', 'min:1', 'max:20'],
            'candidates.*' => ['required', 'integer', 'min:0'],
        ], [
            'candidates.min' => '問題を作る用語を1件以上選択してください。',
            'candidates.max' => '一度に問題を作れる用語は20件までです。',
        ]);
        $draft = $this->pendingDraft($request, $token);
        $availableCandidates = collect($draft->selected_terms);
        $selectedCandidates = collect($validated['candidates'])
            ->unique()
            ->map(fn (int $index): ?array => $availableCandidates->get($index))
            ->filter(fn (?array $candidate): bool => $candidate !== null)
            ->values();

        if ($selectedCandidates->count() !== count(array_unique($validated['candidates']))) {
            return back()->withErrors(['candidates' => '選択された学習候補が不正です。']);
        }

        try {
            $generatedQuiz = $quizGenerationService->generate($draft->source_title, null, $selectedCandidates->all());
            $draft->update([
                'subject' => $generatedQuiz['subject'],
                'topic' => $generatedQuiz['topic'],
                'selected_terms' => $selectedCandidates->all(),
                'generated_questions' => $generatedQuiz['questions'],
            ]);

            return redirect()->route('extension-quiz-drafts.quiz', $draft->token);
        } catch (QuizGenerationException $exception) {
            return back()->withErrors(['candidates' => $exception->getMessage()]);
        }
    }

    private function pendingDraft(Request $request, string $token): ExtensionQuizDraft
    {
        return $request->user()->extensionQuizDrafts()
            ->where('token', $token)
            ->where('source_type', 'screenshot')
            ->whereJsonLength('generated_questions', 0)
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }

    /** @return array{title: string, candidates: array<int, array{term: string, description: string}>} */
    private function analyzeImage(string $contents, ?string $mimeType, string $model, string $apiKey, int $userId): array
    {
        $prompt = 'このスクリーンショットを直接解析し、学習価値が高い重要な概念・用語・要点を最大20件抽出してください。タイトルは画像内容を表す簡潔な日本語にし、各候補のtermと初心者向けdescriptionを日本語で返してください。画像内に学習可能な内容がなければ候補を返さないでください。';
        $response = Http::withHeaders(['x-goog-api-key' => $apiKey, 'Content-Type' => 'application/json'])
            ->acceptJson()->connectTimeout(10)->timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [['role' => 'user', 'parts' => [
                    ['text' => $prompt],
                    ['inlineData' => ['mimeType' => $mimeType, 'data' => base64_encode($contents)]],
                ]]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 3000,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'candidates' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                                'term' => ['type' => 'string'], 'description' => ['type' => 'string'],
                            ], 'required' => ['term', 'description']]],
                        ],
                        'required' => ['title', 'candidates'],
                    ],
                ],
            ]);
        $usage = $response->json('usageMetadata', []);

        if ($response->failed()) {
            $this->recordUsage($userId, $model, mb_strlen($prompt), $usage, false, $response->status(), mb_substr($response->body(), 0, 5000));
            $response->throw();
        }

        $outputText = $response->json('candidates.0.content.parts.0.text');

        try {
            $data = json_decode(is_string($outputText) ? $outputText : '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->recordUsage($userId, $model, mb_strlen($prompt), $usage, false, $response->status(), $exception->getMessage());
            throw $exception;
        }

        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:500'],
            'candidates' => ['required', 'array', 'min:1', 'max:20'],
            'candidates.*.term' => ['required', 'string', 'max:200'],
            'candidates.*.description' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            $this->recordUsage($userId, $model, mb_strlen($prompt), $usage, false, $response->status(), $validator->errors()->first());
            throw new RuntimeException($validator->errors()->first());
        }

        $this->recordUsage($userId, $model, mb_strlen($prompt), $usage, true, $response->status());

        return $validator->validated();
    }

    /** @param array<string, mixed> $usage */
    private function recordUsage(int $userId, string $model, int $inputCharacters, array $usage, bool $success, ?int $httpStatus, ?string $errorMessage = null): void
    {
        try {
            AiUsageLog::query()->create([
                'user_id' => $userId, 'provider' => 'gemini', 'model' => $model, 'feature' => 'screenshot_analysis',
                'input_characters' => $inputCharacters, 'input_tokens' => $usage['promptTokenCount'] ?? null,
                'output_tokens' => $usage['candidatesTokenCount'] ?? null, 'total_tokens' => $usage['totalTokenCount'] ?? null,
                'success' => $success, 'http_status' => $httpStatus, 'error_message' => $errorMessage,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
