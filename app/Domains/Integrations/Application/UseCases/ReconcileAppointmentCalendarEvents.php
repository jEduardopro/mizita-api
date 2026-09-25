<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\UseCases;

use App\Domains\Integrations\Application\Dtos\ReconcileAppointmentCalendarEventsInput;
use App\Domains\Integrations\Contracts\AppointmentFeed;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarEventLinkRepository;
use App\Domains\Integrations\Contracts\CalendarEventPublisher;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Entities\CalendarEventLink;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\ValueObjects\AppointmentSnapshot;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class ReconcileAppointmentCalendarEvents
{
    private const PROVIDER = CalendarProvider::Google;

    public function __construct(
        private readonly AppointmentFeed $appointments,
        private readonly CalendarConnectionRepository $connections,
        private readonly CalendarEventLinkRepository $links,
        private readonly CalendarEventPublisher $publisher,
        private readonly IdGenerator $ids,
        private readonly string $mizitaLink,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(ReconcileAppointmentCalendarEventsInput $input): UseCaseResponse
    {
        try {
            $appointment = $this->appointments->snapshotOf($input->appointmentId);
            $target = $this->targetConnectionFor($appointment);
            $kept = $this->withdrawLinksOutside($appointment, $target);

            if ($target !== null) {
                $this->publish($appointment, $target, $kept);
            }
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success();
    }

    private function targetConnectionFor(AppointmentSnapshot $appointment): ?CalendarConnection
    {
        if (! $appointment->isActive()) {
            return null;
        }

        $connection = $this->connections->findForStaffMember(
            $appointment->businessId,
            $appointment->staffMemberId,
            self::PROVIDER,
        );

        return $connection?->acceptsSync() === true ? $connection : null;
    }

    private function withdrawLinksOutside(AppointmentSnapshot $appointment, ?CalendarConnection $target): ?CalendarEventLink
    {
        $kept = null;

        foreach ($this->links->forAppointment($appointment->businessId, $appointment->appointmentId) as $link) {
            if ($kept === null && $target !== null && $link->belongsTo($target->id)) {
                $kept = $link;

                continue;
            }

            $this->withdraw($link);
        }

        return $kept;
    }

    private function withdraw(CalendarEventLink $link): void
    {
        $connection = $this->connections->findInBusiness($link->businessId, $link->connectionId);

        if ($connection === null) {
            $this->forget($link);

            return;
        }

        if ($connection->acceptsSync()) {
            $this->withdrawRemoteEvent($connection, $link);
        }
    }

    private function withdrawRemoteEvent(CalendarConnection $connection, CalendarEventLink $link): void
    {
        try {
            $this->publisher->withdraw($connection, $link->externalEventId());
        } catch (CalendarAuthorizationRevoked) {
            $this->requireReconnect($connection);

            return;
        }

        $this->forget($link);
    }

    private function forget(CalendarEventLink $link): void
    {
        $this->links->delete($link->businessId, $link->id);
    }

    private function publish(AppointmentSnapshot $appointment, CalendarConnection $target, ?CalendarEventLink $kept): void
    {
        try {
            $externalEventId = $this->publisher->publish(
                $target,
                $kept?->externalEventId(),
                $appointment->draftFor($this->mizitaLink),
            );
        } catch (CalendarAuthorizationRevoked) {
            $this->requireReconnect($target);

            return;
        }

        $this->remember($appointment, $target, $kept, $externalEventId);
    }

    private function remember(
        AppointmentSnapshot $appointment,
        CalendarConnection $target,
        ?CalendarEventLink $kept,
        string $externalEventId,
    ): void {
        if ($kept === null) {
            $this->links->save(CalendarEventLink::create(
                id: $this->ids->next(),
                businessId: $appointment->businessId,
                connectionId: $target->id,
                appointmentId: $appointment->appointmentId,
                externalEventId: $externalEventId,
            ));

            return;
        }

        $kept->repointTo($externalEventId);
        $this->links->save($kept);
    }

    private function requireReconnect(CalendarConnection $connection): void
    {
        $connection->requireReconnect();
        $this->connections->update($connection);
    }
}
