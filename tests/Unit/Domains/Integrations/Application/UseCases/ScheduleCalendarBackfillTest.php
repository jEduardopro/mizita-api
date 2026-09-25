<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\ScheduleCalendarBackfillInput;
use App\Domains\Integrations\Application\UseCases\ScheduleCalendarBackfill;
use Tests\Support\FakeClock;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeAppointmentSyncQueue;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarEventLinkRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeUpcomingAppointments;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;

beforeEach(function () {
    $this->connections = new FakeCalendarConnectionRepository;
    $this->upcoming = new FakeUpcomingAppointments(
        IntegrationsFixtures::APPOINTMENT_ID,
        IntegrationsFixtures::SECOND_APPOINTMENT_ID,
        IntegrationsFixtures::THIRD_APPOINTMENT_ID,
    );
    $this->queue = new FakeAppointmentSyncQueue;
    $this->links = new FakeCalendarEventLinkRepository;

    $this->linkAppointments = function (string ...$appointmentIds): void {
        foreach ($appointmentIds as $position => $appointmentId) {
            $this->links->store(IntegrationsFixtures::link(
                id: sprintf('01930000-0000-7000-8000-0000000009%02d', $position),
                appointmentId: $appointmentId,
            ));
        }
    };

    $this->backfill = fn (
        string $businessId = IntegrationsFixtures::BUSINESS_ID,
        string $connectionId = IntegrationsFixtures::CONNECTION_ID,
    ) => (new ScheduleCalendarBackfill(
        $this->connections,
        $this->upcoming,
        $this->queue,
        new FakeClock(IntegrationsFixtures::now()),
        $this->links,
    ))->handle(new ScheduleCalendarBackfillInput($businessId, $connectionId));
});

describe('a connected calendar', function () {
    beforeEach(function () {
        $this->connections->store(IntegrationsFixtures::connection());
    });

    it('schedules one sync per upcoming active appointment', function () {
        ($this->backfill)();

        expect($this->queue->scheduled)->toBe([
            IntegrationsFixtures::APPOINTMENT_ID,
            IntegrationsFixtures::SECOND_APPOINTMENT_ID,
            IntegrationsFixtures::THIRD_APPOINTMENT_ID,
        ]);
    });

    it('answers with how many syncs it scheduled', function () {
        expect(($this->backfill)()->value())->toBe(3);
    });

    it('asks for the appointments of the connection staff member from the injected now', function () {
        ($this->backfill)();

        expect($this->upcoming->lookups)->toHaveCount(1)
            ->and($this->upcoming->lookups[0]['businessId'])->toBe(IntegrationsFixtures::BUSINESS_ID)
            ->and($this->upcoming->lookups[0]['staffMemberId'])->toBe(IntegrationsFixtures::STAFF_MEMBER_ID)
            ->and($this->upcoming->lookups[0]['now'])->toEqual(IntegrationsFixtures::now());
    });

    it('reads the connection inside the business of the input', function () {
        ($this->backfill)();

        expect($this->connections->businessLookups)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'id' => IntegrationsFixtures::CONNECTION_ID,
        ]]);
    });

    it('schedules nothing and answers zero when no appointment is upcoming', function () {
        $this->upcoming = new FakeUpcomingAppointments;

        expect(($this->backfill)()->value())->toBe(0)
            ->and($this->queue->scheduled)->toBe([]);
    });
});

describe('a connected calendar that already holds published events', function () {
    beforeEach(function () {
        $this->connections->store(IntegrationsFixtures::connection());
    });

    it('also schedules the appointments linked to the calendar that are no longer upcoming', function () {
        $this->upcoming = new FakeUpcomingAppointments(IntegrationsFixtures::APPOINTMENT_ID);
        ($this->linkAppointments)(IntegrationsFixtures::THIRD_APPOINTMENT_ID);

        ($this->backfill)();

        expect($this->queue->scheduled)->toBe([
            IntegrationsFixtures::APPOINTMENT_ID,
            IntegrationsFixtures::THIRD_APPOINTMENT_ID,
        ]);
    });

    it('schedules the upcoming appointments before the linked ones', function () {
        $this->upcoming = new FakeUpcomingAppointments(IntegrationsFixtures::THIRD_APPOINTMENT_ID);
        ($this->linkAppointments)(IntegrationsFixtures::APPOINTMENT_ID, IntegrationsFixtures::SECOND_APPOINTMENT_ID);

        ($this->backfill)();

        expect($this->queue->scheduled)->toBe([
            IntegrationsFixtures::THIRD_APPOINTMENT_ID,
            IntegrationsFixtures::APPOINTMENT_ID,
            IntegrationsFixtures::SECOND_APPOINTMENT_ID,
        ]);
    });

    it('schedules an appointment both upcoming and linked once, at its upcoming position', function () {
        $this->upcoming = new FakeUpcomingAppointments(
            IntegrationsFixtures::SECOND_APPOINTMENT_ID,
            IntegrationsFixtures::APPOINTMENT_ID,
        );
        ($this->linkAppointments)(
            IntegrationsFixtures::THIRD_APPOINTMENT_ID,
            IntegrationsFixtures::APPOINTMENT_ID,
            IntegrationsFixtures::SECOND_APPOINTMENT_ID,
        );

        ($this->backfill)();

        expect($this->queue->scheduled)->toBe([
            IntegrationsFixtures::SECOND_APPOINTMENT_ID,
            IntegrationsFixtures::APPOINTMENT_ID,
            IntegrationsFixtures::THIRD_APPOINTMENT_ID,
        ]);
    });

    it('answers with how many distinct syncs it scheduled', function () {
        ($this->linkAppointments)(IntegrationsFixtures::APPOINTMENT_ID, IntegrationsFixtures::SECOND_APPOINTMENT_ID);

        expect(($this->backfill)()->value())->toBe(3)
            ->and($this->queue->scheduled)->toHaveCount(3);
    });

    it('schedules the linked appointments alone when none is upcoming', function () {
        $this->upcoming = new FakeUpcomingAppointments;
        ($this->linkAppointments)(IntegrationsFixtures::SECOND_APPOINTMENT_ID);

        expect(($this->backfill)()->value())->toBe(1)
            ->and($this->queue->scheduled)->toBe([IntegrationsFixtures::SECOND_APPOINTMENT_ID]);
    });

    it('asks for the links of the connection inside its business', function () {
        ($this->backfill)();

        expect($this->links->connectionLookups)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'connectionId' => IntegrationsFixtures::CONNECTION_ID,
        ]]);
    });

    it('ignores the links of another calendar', function () {
        $this->upcoming = new FakeUpcomingAppointments;
        $this->links->store(IntegrationsFixtures::link(
            connectionId: IntegrationsFixtures::SECOND_CONNECTION_ID,
            appointmentId: IntegrationsFixtures::THIRD_APPOINTMENT_ID,
        ));

        expect(($this->backfill)()->value())->toBe(0)
            ->and($this->queue->scheduled)->toBe([]);
    });
});

describe('a connection that cannot be synced', function () {
    it('answers zero and schedules nothing', function (Closure $arrange) {
        $arrange($this);
        ($this->linkAppointments)(IntegrationsFixtures::APPOINTMENT_ID);

        $response = ($this->backfill)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe(0)
            ->and($this->queue->scheduled)->toBe([])
            ->and($this->upcoming->lookups)->toBe([])
            ->and($this->links->connectionLookups)->toBe([]);
    })->with([
        'no such connection' => [fn (object $test) => null],
        'needs reconnecting' => [fn (object $test) => $test->connections->store(IntegrationsFixtures::awaitingReconnect())],
        'connection of another business' => [fn (object $test) => $test->connections->store(
            IntegrationsFixtures::connection(businessId: IntegrationsFixtures::OTHER_BUSINESS_ID),
        )],
    ]);
});
