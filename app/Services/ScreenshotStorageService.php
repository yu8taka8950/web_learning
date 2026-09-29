<?php

namespace App\Services;

use App\Models\ExtensionQuizDraft;
use App\Models\LearningSet;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScreenshotStorageService
{
    public function deleteIfUnreferenced(?string $path): void
    {
        if (! $this->isManagedPath($path) || $this->isReferenced($path)) {
            return;
        }

        try {
            Storage::disk('local')->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function isManagedPath(?string $path): bool
    {
        return is_string($path)
            && preg_match('#^screenshots/[A-Za-z0-9][A-Za-z0-9._/-]*$#', $path) === 1
            && ! str_contains($path, '..')
            && ! str_starts_with($path, '/');
    }

    private function isReferenced(string $path): bool
    {
        return LearningSet::withTrashed()->where('source_image_path', $path)->exists()
            || ExtensionQuizDraft::query()->where('source_image_path', $path)->exists();
    }
}
