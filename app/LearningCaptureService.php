<?php

namespace App;

use App\Models\LearningCapture;
use App\Models\User;
use App\Services\DictionaryNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningCaptureService
{
    public function __construct(private DictionaryNormalizer $normalizer) {}

    /** @param array<int, array{term: string, explanation?: string|null}> $terms */
    public function capture(User $user, string $pageTitle, string $sourceUrl, array $terms): LearningCapture
    {
        $normalizedUrl = $this->normalizedUrl($sourceUrl);
        $host = parse_url($normalizedUrl, PHP_URL_HOST);

        return DB::transaction(function () use ($user, $pageTitle, $sourceUrl, $normalizedUrl, $host, $terms): LearningCapture {
            $capture = $user->learningCaptures()->firstOrNew(['normalized_source_url' => $normalizedUrl]);
            $capture->fill(['page_title' => Str::limit(trim($pageTitle), 500, ''), 'source_url' => $sourceUrl, 'source_host' => is_string($host) ? $host : null, 'captured_at' => now(), 'last_analyzed_at' => now()])->save();
            $existingTerms = $capture->terms()->get()->keyBy('normalized_term');
            $nextOrder = ((int) $capture->terms()->max('sort_order')) + 1;
            foreach ($terms as $item) {
                $term = $this->normalizer->display($item['term']);
                $key = $this->normalizer->key($term);
                $explanation = isset($item['explanation']) && is_string($item['explanation']) ? Str::limit(trim($item['explanation']), 2000, '') : null;
                if ($term === '' || $key === '') {
                    continue;
                }
                $existing = $existingTerms->get($key);
                if ($existing) {
                    if (($existing->explanation === null || $existing->explanation === '') && $explanation !== '') {
                        $existing->update(['explanation' => $explanation]);
                    }

                    continue;
                }
                $capture->terms()->create(['term' => $term, 'normalized_term' => $key, 'explanation' => $explanation ?: null, 'sort_order' => $nextOrder++]);
            }

            return $capture->fresh('terms');
        });
    }

    private function normalizedUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) && ! (($scheme === 'http' && $parts['port'] === 80) || ($scheme === 'https' && $parts['port'] === 443)) ? ':'.$parts['port'] : '';
        $path = (string) ($parts['path'] ?? '/');
        $path = $path === '/' ? $path : rtrim($path, '/');
        $query = collect(explode('&', (string) ($parts['query'] ?? '')))
            ->filter(fn (string $parameter): bool => $parameter !== '')
            ->reject(function (string $parameter): bool {
                $name = strtolower((string) strstr($parameter, '=', true));

                return $name === '' ? in_array(strtolower($parameter), ['gclid', 'fbclid'], true) : str_starts_with($name, 'utm_') || in_array($name, ['gclid', 'fbclid', 'mc_cid', 'mc_eid'], true);
            })
            ->implode('&');

        return $scheme.'://'.$host.$port.$path.($query !== '' ? '?'.$query : '');
    }
}
