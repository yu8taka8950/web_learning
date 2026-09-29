<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\MonthlyFeatureUsage;
use App\Models\UsageReservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsageLimitService
{
    public const Web = 'web_analysis';

    public const Screenshot = 'screenshot_analysis';

    public function currentPlan(User $user): string
    {
        return $user->subscribed('plus') ? 'plus' : 'free';
    }

    public function limit(User $user, string $feature): int
    {
        $this->assertFeature($feature);

        return (int) config('plans.'.$this->currentPlan($user).'.'.$this->planFeature($feature));
    }

    /** @return array{feature: string, used: int, limit: int, remaining: int, plan: string, warning: bool} */
    public function usage(User $user, string $feature): array
    {
        $this->assertFeature($feature);

        $used = AiUsageLog::query()->where('user_id', $user->id)->where('feature', $this->logFeature($feature))->where('success', true)
            ->whereBetween('created_at', [$this->periodStart(), $this->periodStart()->copy()->addMonth()])->count();
        $limit = $this->limit($user, $feature);

        return ['feature' => $feature, 'used' => $used, 'limit' => $limit, 'remaining' => max(0, $limit - $used), 'plan' => $this->currentPlan($user), 'warning' => $used >= (int) ceil($limit * 0.9)];
    }

    /** @return array{token: string, usage: array{feature: string, used: int, limit: int, remaining: int, plan: string, warning: bool}} */
    public function reserve(User $user, string $feature): array
    {
        return DB::transaction(function () use ($user, $feature): array {
            $periodStart = $this->periodStart()->toDateString();
            DB::table('monthly_feature_usages')->insertOrIgnore([
                'user_id' => $user->id,
                'feature' => $feature,
                'period_starts_at' => $periodStart,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $usage = MonthlyFeatureUsage::query()->where([
                'user_id' => $user->id,
                'feature' => $feature,
                'period_starts_at' => $periodStart,
            ])->lockForUpdate()->firstOrFail();
            $usage->reservations()->where('expires_at', '<=', now())->delete();
            $summary = $this->usage($user, $feature);
            $reserved = $usage->reservations()->where('expires_at', '>', now())->count();
            if ($summary['limit'] <= $summary['used'] + $reserved) {
                throw new UsageLimitReachedException($summary);
            }
            $token = (string) Str::uuid();
            $usage->reservations()->create(['token' => $token, 'expires_at' => now()->addMinutes(3)]);

            return ['token' => $token, 'usage' => $summary];
        }, 3);
    }

    public function complete(string $token): void
    {
        DB::transaction(function () use ($token): void {
            $reservation = UsageReservation::query()->where('token', $token)->lockForUpdate()->first();
            if ($reservation !== null) {
                $reservation->delete();
            }
        }, 3);
    }

    public function release(string $token): void
    {
        UsageReservation::query()->where('token', $token)->delete();
    }

    private function periodStart(): Carbon
    {
        return now()->startOfMonth();
    }

    private function planFeature(string $feature): string
    {
        return $feature === self::Web ? 'web' : 'screenshot';
    }

    private function logFeature(string $feature): string
    {
        return $feature === self::Web ? 'web_term_detection' : 'screenshot_analysis';
    }

    private function assertFeature(string $feature): void
    {
        if (! in_array($feature, [self::Web, self::Screenshot], true)) {
            throw new \InvalidArgumentException('Unsupported usage feature.');
        }
    }
}
