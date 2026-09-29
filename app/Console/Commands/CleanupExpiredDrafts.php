<?php

namespace App\Console\Commands;

use App\Models\ExtensionQuizDraft;
use App\Services\ScreenshotStorageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('web-learning:cleanup-expired-drafts')]
#[Description('Delete expired extension quiz drafts and unreferenced screenshots')]
class CleanupExpiredDrafts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScreenshotStorageService $screenshots): int
    {
        ExtensionQuizDraft::query()->where('expires_at', '<=', now())->orderBy('id')->each(function (ExtensionQuizDraft $draft) use ($screenshots): void {
            $path = $draft->source_image_path;
            $draft->delete();
            $screenshots->deleteIfUnreferenced($path);
        });

        return self::SUCCESS;
    }
}
