<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\UseCases;

use App\Domains\Integrations\Application\Dtos\BusyIntervalData;
use App\Domains\Integrations\Application\Dtos\ListCalendarBusyIntervalsInput;
use App\Domains\Integrations\Contracts\BusinessProfiles;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarEventFeed;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Exceptions\ExternalCalendarUnavailable;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ExternalCalendarEvent;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use DateTimeZone;

final class ListCalendarBusyIntervals
{
    private const PROVIDER = CalendarProvider::Google;

    public function __construct(
        private readonly CalendarConnectionRepository $connections,
        private readonly BusinessProfiles $businesses,
        private readonly CalendarEventFeed $feed,
    ) {}

    /**
     * @return UseCaseResponse<list<BusyIntervalData>>
     */
    public function handle(ListCalendarBusyIntervalsInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $connection = $this->connections->findForStaffMember($input->businessId, $input->staffMemberId, self::PROVIDER);

            if ($connection?->acceptsSync() !== true) {
                return UseCaseResponse::success([]);
            }

            $businessZone = $this->businesses->profileOf($input->businessId)->localZone();

            return UseCaseResponse::success($this->busyIntervalsOf($this->eventsOf($connection, $input), $businessZone));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @return list<ExternalCalendarEvent>
     */
    private function eventsOf(CalendarConnection $connection, ListCalendarBusyIntervalsInput $input): array
    {
        try {
            return $this->feed->eventsBetween($connection, $input->from, $input->to);
        } catch (ExternalCalendarUnavailable|CalendarAuthorizationRevoked) {
            return [];
        }
    }

    /**
     * @param  list<ExternalCalendarEvent>  $events
     * @return list<BusyIntervalData>
     */
    private function busyIntervalsOf(array $events, DateTimeZone $businessZone): array
    {
        $intervals = [];

        foreach ($events as $event) {
            if (! $event->blocksTime()) {
                continue;
            }

            $intervals[] = BusyIntervalData::fromInterval($event->intervalIn($businessZone));
        }

        return $intervals;
    }
}
