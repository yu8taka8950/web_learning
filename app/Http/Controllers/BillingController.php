<?php

namespace App\Http\Controllers;

use App\Services\UsageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function pricing(Request $request, UsageLimitService $usageLimits): View
    {
        return view('billing.pricing', [
            'user' => $request->user(),
            'webUsage' => $usageLimits->usage($request->user(), UsageLimitService::Web),
            'screenshotUsage' => $usageLimits->usage($request->user(), UsageLimitService::Screenshot),
        ]);
    }

    public function confirm(Request $request): View
    {
        $validated = $request->validate(['currency' => ['required', 'in:jpy,usd']]);

        return view('billing.confirm', [
            'currency' => $validated['currency'],
            'isPlus' => $request->user()->subscribed('plus'),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'in:jpy,usd'],
            'accepted_recurring' => ['accepted'],
            'accepted_terms' => ['accepted'],
        ]);

        if ($request->user()->subscribed('plus')) {
            return redirect()->route('pricing')->with('status', '現在Plusをご利用中です。');
        }

        $price = config('plans.prices.'.$validated['currency']);

        abort_unless(is_string($price) && $price !== '', 503, 'Stripe price is not configured.');

        return $request->user()->newSubscription('plus', $price)->checkout([
            'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.cancel'),
        ])->redirect();
    }

    public function success(): View
    {
        return view('billing.success');
    }

    public function cancel(): RedirectResponse
    {
        return redirect()->route('pricing')->with('status', '決済は行われませんでした。');
    }

    public function portal(Request $request): RedirectResponse
    {
        abort_unless($request->user()->subscribed('plus'), 403);

        return $request->user()->redirectToBillingPortal(route('pricing'));
    }
}
