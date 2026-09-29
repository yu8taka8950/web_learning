<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use Illuminate\View\View;

class AiUsageController extends Controller
{
    public function index(): View
    {
        $today = now();
        $todayUsage = AiUsageLog::query()
            ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->toBase()
            ->selectRaw('COUNT(*) as api_calls')
            ->selectRaw('COALESCE(SUM(CASE WHEN success = ? THEN 1 ELSE 0 END), 0) as successful_calls', [true])
            ->selectRaw('COALESCE(SUM(CASE WHEN success = ? THEN 1 ELSE 0 END), 0) as failed_calls', [false])
            ->selectRaw('COALESCE(SUM(input_characters), 0) as input_characters')
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->first();

        $allTimeUsage = AiUsageLog::query()
            ->toBase()
            ->selectRaw('COUNT(*) as api_calls')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->first();

        return view('ai-usage.index', [
            'todayUsage' => [
                'apiCalls' => (int) $todayUsage->api_calls,
                'successfulCalls' => (int) $todayUsage->successful_calls,
                'failedCalls' => (int) $todayUsage->failed_calls,
                'inputCharacters' => (int) $todayUsage->input_characters,
                'inputTokens' => (int) $todayUsage->input_tokens,
                'outputTokens' => (int) $todayUsage->output_tokens,
                'totalTokens' => (int) $todayUsage->total_tokens,
            ],
            'allTimeUsage' => [
                'apiCalls' => (int) $allTimeUsage->api_calls,
                'totalTokens' => (int) $allTimeUsage->total_tokens,
            ],
            // Chrome拡張のBearer Token経由のログも確認できるよう、user_idでは絞り込みません。
            'recentUsageLogs' => AiUsageLog::query()
                ->select(['id', 'provider', 'model', 'feature', 'input_characters', 'input_tokens', 'output_tokens', 'total_tokens', 'success', 'http_status', 'error_message', 'created_at'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }
}
