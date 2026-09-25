<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\UseCases;

use App\Domains\Integrations\Application\Dtos\ScheduleCalendarBackfillInput;
use App\Domains\Integrations\Contracts\AppointmentSyncQueue;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarEventLinkRepository;
use App\Domains\Integrations\Contracts\UpcomingAppointments;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;

final class ScheduleCalendarBackfill
{
    public function __construct(
        private readonly CalendarConnectionRepository $connections,
        private readonly UpcomingAppointments $upcoming,
        private readonly AppointmentSyncQueue $syncQueue,
        private readonly Clock $clock,
        private readonly CalendarEventLinkRepository $links,
    ) {}

    /**
     * @return UseCaseResponse<int>
     */
    public function handle(ScheduleCalendarBackfillInput $input): UseCaseResponse
    {
        $connection = $this->connections->findInBusiness($input->businessId, $input->connectionId);

        if ($connection?->acceptsSync() !== true) {
            return UseCaseResponse::success(0);
        }

        $appointmentIds = $this->appointmentIdsToReconcile($connection);

        foreach ($appointmentIds as $appointmentId) {
            $this->syncQueue->schedule($appointmentId);
        }

        return UseCaseResponse::success(count($appointmentIds));
    }

    /**
     * @return list<string>
     */
    private function appointmentIdsToReconcile(CalendarConnection $connection): array
    {
        return array_values(array_unique([
            ...$this->upcoming->activeIdsFor($connection->businessId, $connection->staffMemberId, $this->clock->now()),
            ...$this->links->appointmentIdsLinkedTo($connection->businessId, $connection->id),
        ]));
    }
}
