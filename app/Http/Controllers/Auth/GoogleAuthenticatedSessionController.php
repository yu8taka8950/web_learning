<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthenticatedSessionController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $googleId = $googleUser->getId();
            $email = $googleUser->getEmail();

            if (! is_string($googleId) || $googleId === '' || ! is_string($email) || $email === '') {
                return $this->failedLogin();
            }

            $user = User::query()->where('google_id', $googleId)->first();

            if ($user === null) {
                $email = Str::lower($email);

                if (User::query()->where('email', $email)->exists()) {
                    return redirect()->route('login')->withErrors([
                        'email' => 'このメールアドレスはすでに登録されています。通常の方法でログイン後、Googleアカウントを連携してください。',
                    ]);
                }

                $rawUser = $googleUser->getRaw();
                $name = $googleUser->getName();

                $user = User::query()->create([
                    'name' => is_string($name) && $name !== '' ? $name : Str::before($email, '@'),
                    'email' => $email,
                    'google_id' => $googleId,
                    'password' => null,
                ]);

                if (($rawUser['email_verified'] ?? false) === true) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(
                $user->onboarding_completed_at === null
                    ? route('onboarding.show')
                    : route('dashboard'),
            );
        } catch (QueryException) {
            return $this->failedLogin();
        } catch (\Throwable) {
            return $this->failedLogin();
        }
    }

    private function failedLogin(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => 'Googleでのログインを完了できませんでした。もう一度お試しください。',
        ]);
    }
}
