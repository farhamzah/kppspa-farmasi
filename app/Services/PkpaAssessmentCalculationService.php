<?php

namespace App\Services;

use App\Models\PkpaRotationAssessment;
use App\Models\PkpaRotationComponentScore;

class PkpaAssessmentCalculationService
{
    public function calculateComponent(PkpaRotationComponentScore $score, string $rawScore, string $maximumRawScore, string $maximumScore): array
    {
        $raw = $this->toScaled($rawScore);
        $maxRaw = max(1, $this->toScaled($maximumRawScore));
        $maxScore = $this->toScaled($maximumScore);
        $weight = $this->toScaled($score->weight_percentage_snapshot);

        $normalized = intdiv($raw * $maxScore, $maxRaw);
        $weighted = intdiv($normalized * $weight, 1000000);

        return [
            'raw_score' => $this->fromScaled($raw),
            'normalized_score' => $this->fromScaled($normalized),
            'weighted_score' => $this->fromScaled($weighted),
        ];
    }

    public function total(PkpaRotationAssessment $assessment): array
    {
        $assessment->loadMissing('componentScores');
        $sum = 0;
        foreach ($assessment->componentScores as $score) {
            $sum += $this->toScaled($score->weighted_score ?? '0');
        }

        $rounded = $this->roundScaled($sum, $assessment->rounding_precision_snapshot, $assessment->rounding_mode_snapshot);

        return [
            'raw_total_score' => $this->fromScaled($sum),
            'final_score' => $this->fromScaled($rounded),
            'formula' => 'sum(normalized_score * weight_percentage / 100)',
            'rounding' => [
                'precision' => $assessment->rounding_precision_snapshot,
                'mode' => $assessment->rounding_mode_snapshot,
            ],
        ];
    }

    private function toScaled(string|int|float|null $value): int
    {
        $value = (string) ($value ?? '0');
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        $scaled = ((int) $whole * 10000) + (int) str_pad(substr($decimal, 0, 4), 4, '0');

        return $negative ? -$scaled : $scaled;
    }

    private function fromScaled(int $value): string
    {
        $negative = $value < 0;
        $value = abs($value);

        return ($negative ? '-' : '').intdiv($value, 10000).'.'.str_pad((string) ($value % 10000), 4, '0', STR_PAD_LEFT);
    }

    private function roundScaled(int $value, int $precision, string $mode): int
    {
        $factor = 10 ** max(0, 4 - $precision);
        $base = intdiv($value, $factor) * $factor;
        $remainder = abs($value - $base);

        return match ($mode) {
            'floor' => $base,
            'ceil' => $remainder > 0 ? $base + ($value >= 0 ? $factor : -$factor) : $base,
            'half_even' => $remainder * 2 > $factor || ($remainder * 2 === $factor && intdiv(abs($base), $factor) % 2 === 1)
                ? $base + ($value >= 0 ? $factor : -$factor)
                : $base,
            default => $remainder * 2 >= $factor ? $base + ($value >= 0 ? $factor : -$factor) : $base,
        };
    }
}
