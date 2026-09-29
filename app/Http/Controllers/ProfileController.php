<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\ExtensionQuizDraft;
use App\Models\LearningSet;
use App\Models\User;
use App\Services\ScreenshotStorageService;
use App\Services\UsageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, UsageLimitService $usageLimits): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'webUsage' => $usageLimits->usage($request->user(), UsageLimitService::Web),
            'screenshotUsage' => $usageLimits->usage($request->user(), UsageLimitService::Screenshot),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->password === null) {
            abort_unless((int) $request->session()->get('google-delete-confirmed-at', 0) >= now()->subMinutes(10)->timestamp, 403);
        } else {
            $request->validateWithBag('userDeletion', ['password' => ['required', 'current_password']]);
        }

        $subscription = $user->subscription('plus');
        try {
            if ($subscription !== null && ! $subscription->ended()) {
                $subscription->cancelNow();
            }
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['account' => 'Plus契約を終了できなかったため、アカウントは削除されませんでした。']);
        }

        $paths = LearningSet::withTrashed()->where('user_id', $user->id)->pluck('source_image_path')
            ->merge(ExtensionQuizDraft::query()->where('user_id', $user->id)->pluck('source_image_path'))
            ->filter()->unique()->all();

        DB::transaction(function () use ($user): void {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table'))->where('user_id', $user->id)->delete();
            }
            User::query()->whereKey($user->id)->delete();
        });

        Auth::logout();

        foreach ($paths as $path) {
            app(ScreenshotStorageService::class)->deleteIfUnreferenced($path);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->forget(['google-delete-user-id', 'google-delete-confirmed-at']);

        return Redirect::to('/');
    }

    public function createExtensionToken(Request $request): RedirectResponse
    {
        $plainTextToken = 'wlcap_'.Str::random(48);
        $request->user()->extensionAccessTokens()->delete();
        $request->user()->extensionAccessTokens()->create(['token_hash' => hash('sha256', $plainTextToken)]);

        return Redirect::route('profile.edit')->with('extension-token', $plainTextToken);
    }

    public function destroyExtensionToken(Request $request): RedirectResponse
    {
        $request->user()->extensionAccessTokens()->delete();

        return Redirect::route('profile.edit')->with('status', 'extension-token-revoked');
    }
}
