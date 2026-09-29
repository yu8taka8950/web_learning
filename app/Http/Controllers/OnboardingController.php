<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('onboarding', [
            'isGuest' => $user === null,
            'isOnboardingComplete' => $user?->onboarding_completed_at !== null,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'onboarding_completed_at' => now(),
        ])->save();

        return redirect()->route('dashboard');
    }
}
