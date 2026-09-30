<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Application\Dtos;

use App\Domains\Statistics\ValueObjects\ReportingWindow;

final readonly class PeriodCollectionData
{
    public function __construct(
        public string $from,
        public string $to,
        public int $collectedCents,
    ) {}

    public static function of(ReportingWindow $window, int $collectedCents): self
    {
        return new self($window->from->toString(), $window->to->toString(), $collectedCents);
    }
}
