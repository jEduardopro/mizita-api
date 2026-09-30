<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

final readonly class StatisticsWindows
{
    public function __construct(
        public ReportingWindow $current,
        public ReportingWindow $previous,
        public ReportingWindow $today,
        public ReportingWindow $lastSevenDays,
    ) {}
}
