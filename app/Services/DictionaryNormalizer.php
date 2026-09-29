<?php

namespace App\Services;

use Normalizer;

class DictionaryNormalizer
{
    public function display(string $value): string
    {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value;

        return trim((string) preg_replace('/\s+/u', ' ', $normalized));
    }

    public function key(string $value): string
    {
        return mb_strtolower($this->display($value));
    }
}
