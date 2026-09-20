<?php

namespace App\Support;

class PkpaPortfolioTextFormatter
{
    public function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => $this->normalize($item))
                ->all();
        }

        if (! is_string($value)) {
            return $value;
        }

        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $value);
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;
        $lines = preg_split('/\n/u', $text) ?: [];
        $normalized = [];
        $previousBlank = false;

        foreach ($lines as $line) {
            $line = str_replace("\t", ' ', $line);
            $line = preg_replace('/[ ]{2,}/u', ' ', trim($line)) ?? trim($line);
            $line = preg_replace('/\s+([,.;:!?])/u', '$1', $line) ?? $line;
            $line = preg_replace('/^[\x{2022}\x{25CF}\x{25AA}\x{25E6}\x{2023}\x{2043}*\x{2013}\x{2014}-]\s*/u', '- ', $line) ?? $line;
            $line = preg_replace('/^\(?([0-9]+)[.)]\s*/u', '$1. ', $line) ?? $line;

            $isBlank = $line === '';
            if ($isBlank && $previousBlank) {
                continue;
            }

            $normalized[] = $line;
            $previousBlank = $isBlank;
        }

        return trim(implode("\n", $normalized));
    }
}
