<?php

namespace App;

use App\Models\ExtensionAccessToken;
use Illuminate\Http\Request;

class ExtensionAccessTokenAuthenticator
{
    public function authenticate(Request $request): ?ExtensionAccessToken
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $accessToken = ExtensionAccessToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('user')
            ->first();

        if ($accessToken !== null) {
            $accessToken->update(['last_used_at' => now()]);
        }

        return $accessToken;
    }
}
