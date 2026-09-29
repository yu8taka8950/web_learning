<?php

namespace App\Services;

class TermExplanationFormatter
{
    public function format(string $term, string $explanation): string
    {
        $trimmedExplanation = trim($explanation);
        $trimmedTerm = trim($term);

        if ($trimmedExplanation === '' || $trimmedTerm === '') {
            return $trimmedExplanation;
        }

        $formatted = preg_replace(
            '/^'.preg_quote($trimmedTerm, '/').'\s*(?:とは|は)\s*[、,：:]?\s*/iu',
            '',
            $trimmedExplanation,
            1,
        );

        return is_string($formatted) && $formatted !== '' ? $formatted : $trimmedExplanation;
    }
}
