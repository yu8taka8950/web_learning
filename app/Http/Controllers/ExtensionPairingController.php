<?php

namespace App\Http\Controllers;

use App\ExtensionAccessTokenAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExtensionPairingController extends Controller
{
    private const LifetimeSeconds = 300;

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pairing_id' => ['required', 'string', 'size:64', 'alpha_num'],
            'pairing_secret' => ['required', 'string', 'size:64', 'alpha_num'],
        ]);

        if (! Cache::add($this->cacheKey($validated['pairing_id']), [
            'secret_hash' => hash('sha256', $validated['pairing_secret']),
            'approved_user_id' => null,
            'expires_at' => now()->addSeconds(self::LifetimeSeconds)->timestamp,
        ], now()->addSeconds(self::LifetimeSeconds))) {
            return response()->json(['message' => '接続の開始に失敗しました。'], 409);
        }

        return response()->json([
            'connect_url' => route('extension.connect.show', $validated['pairing_id']).'#pairing_secret='.$validated['pairing_secret'],
        ], 201);
    }

    public function show(string $pairingId): View
    {
        abort_unless($this->pairing($pairingId) !== null, 404);

        return view('extension.connect', [
            'pairingId' => $pairingId,
            'connected' => false,
            'authenticated' => auth()->check(),
        ]);
    }

    public function authorizePairing(string $pairingId): View
    {
        abort_unless($this->pairing($pairingId) !== null, 404);

        return view('extension.connect', [
            'pairingId' => $pairingId,
            'connected' => false,
            'authenticated' => true,
        ]);
    }

    public function approve(Request $request, string $pairingId): View
    {
        $validated = $request->validate([
            'pairing_secret' => ['required', 'string', 'size:64', 'alpha_num'],
        ]);

        return Cache::lock('extension-pairing-approve:'.$pairingId, 10)->block(3, function () use ($request, $pairingId, $validated): View {
            $pairing = $this->pairing($pairingId);
            abort_unless(
                $pairing !== null
                && $pairing['approved_user_id'] === null
                && hash_equals($pairing['secret_hash'], hash('sha256', $validated['pairing_secret'])),
                404,
            );

            $plainTextToken = 'wlcap_'.Str::random(48);
            $request->user()->extensionAccessTokens()->create([
                'name' => 'Chrome Extension',
                'token_hash' => hash('sha256', $plainTextToken),
            ]);

            Cache::put($this->cacheKey($pairingId), [
                ...$pairing,
                'approved_user_id' => $request->user()->id,
                'access_token' => $plainTextToken,
            ], now()->addSeconds(max(1, $pairing['expires_at'] - now()->timestamp)));

            return view('extension.connect', [
                'pairingId' => $pairingId,
                'connected' => true,
                'authenticated' => true,
            ]);
        });
    }

    public function claim(Request $request, string $pairingId): JsonResponse
    {
        $validated = $request->validate([
            'pairing_secret' => ['required', 'string', 'size:64', 'alpha_num'],
        ]);

        return Cache::lock('extension-pairing-claim:'.$pairingId, 10)->block(3, function () use ($pairingId, $validated): JsonResponse {
            $pairing = $this->pairing($pairingId);

            if ($pairing === null || ! hash_equals($pairing['secret_hash'], hash('sha256', $validated['pairing_secret'])) || ! isset($pairing['access_token'])) {
                return response()->json(['message' => '接続情報が見つかりません。'], 404);
            }

            Cache::forget($this->cacheKey($pairingId));

            return response()->json(['access_token' => $pairing['access_token']]);
        });
    }

    public function disconnect(Request $request, ExtensionAccessTokenAuthenticator $authenticator): JsonResponse
    {
        $accessToken = $authenticator->authenticate($request);

        if ($accessToken === null) {
            return response()->json(['message' => '認証に失敗しました。'], 401);
        }

        $accessToken->delete();

        return response()->json(status: 204);
    }

    public function status(Request $request, ExtensionAccessTokenAuthenticator $authenticator): JsonResponse
    {
        if ($authenticator->authenticate($request) === null) {
            return response()->json(['message' => '認証に失敗しました。'], 401);
        }

        return response()->json(status: 204);
    }

    private function pairing(string $pairingId): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{64}$/', $pairingId)) {
            return null;
        }

        $pairing = Cache::get($this->cacheKey($pairingId));

        if (! is_array($pairing) || ! is_int($pairing['expires_at'] ?? null) || $pairing['expires_at'] <= now()->timestamp) {
            return null;
        }

        return $pairing;
    }

    private function cacheKey(string $pairingId): string
    {
        return 'extension-pairing:'.$pairingId;
    }
}
