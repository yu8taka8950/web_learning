<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GoogleAccountDeletionController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->password === null && $request->user()->google_id !== null, 404);
        $request->session()->put('google-delete-user-id', $request->user()->id);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($request->session()->get('google-delete-user-id') === $user->id, 403);

        try {
            $googleId = Socialite::driver('google')->user()->getId();
        } catch (\Throwable) {
            return redirect()->route('profile.edit')->withErrors(['account' => 'Googleでの本人確認を完了できませんでした。']);
        }

        if (! is_string($googleId) || ! hash_equals((string) $user->google_id, $googleId)) {
            $request->session()->forget('google-delete-user-id');

            return redirect()->route('profile.edit')->withErrors(['account' => '登録中のGoogleアカウントで本人確認してください。']);
        }

        $request->session()->put('google-delete-confirmed-at', now()->timestamp);

        return redirect()->route('profile.edit')->with('status', 'google-delete-confirmed');
    }
}
