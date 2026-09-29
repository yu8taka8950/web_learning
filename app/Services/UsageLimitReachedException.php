<?php

namespace App\Services;

use RuntimeException;

class UsageLimitReachedException extends RuntimeException
{
    public function __construct(public readonly array $usage)
    {
        parent::__construct('Monthly usage limit reached.');
    }
}
