<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Services;

final class PercentageCalculator
{
    private const PRECISION = 2;

    private const HUNDRED = 100;

    public function shareOf(int $part, int $whole): float
    {
        if ($whole === 0) {
            return 0.0;
        }

        return round($part / $whole * self::HUNDRED, self::PRECISION);
    }

    public function changeBetween(int $previous, int $current): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * self::HUNDRED, self::PRECISION);
    }
}
