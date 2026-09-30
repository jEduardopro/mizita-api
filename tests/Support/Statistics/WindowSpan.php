<?php

declare(strict_types=1);

namespace Tests\Support\Statistics;

use App\Domains\Statistics\ValueObjects\ReportingWindow;

final class WindowSpan
{
    /**
     * @return array{from: string, to: string, startsAt: string, endsAt: string}
     */
    public static function of(ReportingWindow $window): array
    {
        return [
            'from' => $window->from->toString(),
            'to' => $window->to->toString(),
            'startsAt' => $window->startsAt->format(DATE_ATOM),
            'endsAt' => $window->endsAt->format(DATE_ATOM),
        ];
    }

    public static function hoursIn(ReportingWindow $window): int
    {
        return intdiv($window->endsAt->getTimestamp() - $window->startsAt->getTimestamp(), 3600);
    }
}
