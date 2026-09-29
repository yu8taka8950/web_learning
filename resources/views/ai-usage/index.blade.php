<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">AI利用状況</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-8 sm:px-6 lg:px-8">
            <section class="space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">今日</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ now()->format('Y年m月d日') }}のGemini API利用量です。</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['API呼び出し', number_format($todayUsage['apiCalls']).'回', 'text-gray-900 dark:text-gray-100'],
                        ['成功', number_format($todayUsage['successfulCalls']).'回', 'text-green-600 dark:text-green-400'],
                        ['失敗', number_format($todayUsage['failedCalls']).'回', 'text-red-600 dark:text-red-400'],
                        ['入力文字数', number_format($todayUsage['inputCharacters']).'文字', 'text-gray-900 dark:text-gray-100'],
                        ['Input Tokens', number_format($todayUsage['inputTokens']), 'text-gray-900 dark:text-gray-100'],
                        ['Output Tokens', number_format($todayUsage['outputTokens']), 'text-gray-900 dark:text-gray-100'],
                        ['Total Tokens', number_format($todayUsage['totalTokens']), 'text-gray-900 dark:text-gray-100'],
                    ] as [$label, $value, $valueClass])
                        <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-semibold {{ $valueClass }}">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">累計</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                        <p class="text-sm text-gray-600 dark:text-gray-400">API呼び出し</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($allTimeUsage['apiCalls']) }}回</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Tokens</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($allTimeUsage['totalTokens']) }}</p>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">最近のAI利用</h3>
                <div class="overflow-x-auto rounded-lg bg-white shadow-sm dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">実行日時</th>
                                <th class="px-4 py-3">Provider</th>
                                <th class="px-4 py-3">Model</th>
                                <th class="px-4 py-3">機能</th>
                                <th class="px-4 py-3 text-right">入力文字数</th>
                                <th class="px-4 py-3 text-right">Input Tokens</th>
                                <th class="px-4 py-3 text-right">Output Tokens</th>
                                <th class="px-4 py-3 text-right">Total Tokens</th>
                                <th class="px-4 py-3">状態</th>
                                <th class="px-4 py-3 text-right">HTTP Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-700 dark:divide-gray-700 dark:text-gray-300">
                            @forelse ($recentUsageLogs as $usageLog)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $usageLog->created_at->format('Y/m/d H:i') }}</td>
                                    <td class="px-4 py-3">{{ $usageLog->provider }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $usageLog->model }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $usageLog->featureLabel() }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($usageLog->input_characters) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($usageLog->input_tokens ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($usageLog->output_tokens ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($usageLog->total_tokens ?? 0) }}</td>
                                    <td class="px-4 py-3">
                                        <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' => $usageLog->success, 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' => ! $usageLog->success])>
                                            {{ $usageLog->success ? '成功' : '失敗' }}
                                        </span>
                                        @if (! $usageLog->success && $usageLog->error_message)
                                            <p class="mt-1 max-w-xs truncate text-xs text-red-600 dark:text-red-400">{{ str($usageLog->error_message)->limit(80) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ $usageLog->http_status ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-6 text-center text-gray-600 dark:text-gray-400">まだAI利用履歴がありません。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
