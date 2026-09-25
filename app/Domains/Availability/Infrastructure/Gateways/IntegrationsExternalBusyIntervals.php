<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\ExternalBusyIntervals;
use App\Domains\Availability\ValueObjects\BookedInterval;
use App\Domains\Integrations\Application\Dtos\BusyIntervalData;
use App\Domains\Integrations\Application\Dtos\ListCalendarBusyIntervalsInput;
use App\Domains\Integrations\Application\UseCases\ListCalendarBusyIntervals;
use DateTimeImmutable;

final class IntegrationsExternalBusyIntervals implements ExternalBusyIntervals
{
    public function __construct(
        private readonly ListCalendarBusyIntervals $listCalendarBusyIntervals,
    ) {}

    /**
     * @return list<BookedInterval>
     */
    public function forStaffBetween(
        string $businessId,
        string $staffId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        $response = $this->listCalendarBusyIntervals->handle(
            new ListCalendarBusyIntervalsInput($businessId, $staffId, $from, $to),
        );

        if ($response->failed()) {
            return [];
        }

        return array_map(self::intervalFrom(...), $response->value());
    }

    private static function intervalFrom(BusyIntervalData $busy): BookedInterval
    {
        return new BookedInterval($busy->startsAt, $busy->endsAt);
    }
}
