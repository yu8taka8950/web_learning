<?php

namespace App\Http\Controllers;

use App\ExtensionAccessTokenAuthenticator;
use App\Http\Requests\StoreExtensionCaptureRequest;
use App\LearningCaptureService;
use Illuminate\Http\JsonResponse;

class ExtensionCaptureController extends Controller
{
    public function store(StoreExtensionCaptureRequest $request, LearningCaptureService $captureService, ExtensionAccessTokenAuthenticator $authenticator): JsonResponse
    {
        $accessToken = $authenticator->authenticate($request);
        if ($accessToken === null) {
            return response()->json(['message' => '認証に失敗しました。'], 401);
        }
        $validated = $request->validated();
        $capture = $captureService->capture($accessToken->user, $validated['page_title'], $validated['source_url'], $validated['terms']);

        return response()->json(['id' => $capture->id, 'term_count' => $capture->terms->count()], 201);
    }
}
