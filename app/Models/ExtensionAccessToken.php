<?php

namespace App\Models;

use Database\Factories\ExtensionAccessTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionAccessToken extends Model
{
    /** @use HasFactory<ExtensionAccessTokenFactory> */
    use HasFactory;

    protected $fillable = ['name', 'token_hash', 'last_used_at', 'expires_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
