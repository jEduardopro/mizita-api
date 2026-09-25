<?php

declare(strict_types=1);

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Integrations\Entities\CalendarEventLink;
use App\Domains\Integrations\Infrastructure\Eloquent\Mappers\CalendarEventLinkMapper;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarEventLinkModel;
use Tests\Support\FakeBusinessContext;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

beforeEach(function () {
    $this->mapper = new CalendarEventLinkMapper;

    $this->row = function (array $overrides = []): CalendarEventLinkModel {
        $connection = new CalendarConnectionModel;
        $connection->setRawAttributes(['id' => 7, 'uuid' => IntegrationsFixtures::CONNECTION_ID], true);

        $appointment = new AppointmentModel;
        $appointment->setRawAttributes(['id' => 9, 'uuid' => IntegrationsFixtures::APPOINTMENT_ID], true);

        $model = new CalendarEventLinkModel;
        $model->setRawAttributes([
            'id' => 3,
            'uuid' => IntegrationsFixtures::LINK_ID,
            'business_id' => 42,
            'calendar_connection_id' => 7,
            'appointment_id' => 9,
            'external_event_id' => 'evt-1',
            ...$overrides,
        ], true);
        $model->setRelation('connection', $connection);
        $model->setRelation('appointment', $appointment);

        return $model;
    };
});

describe('reading a row', function () {
    it('restores the link with every neighbour as its uuid', function () {
        $link = $this->mapper->toEntity(($this->row)(), FakeBusinessContext::BUSINESS_ID);

        expect($link)->toBeInstanceOf(CalendarEventLink::class)
            ->and($link->id)->toBe(IntegrationsFixtures::LINK_ID)
            ->and($link->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($link->connectionId)->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($link->appointmentId)->toBe(IntegrationsFixtures::APPOINTMENT_ID)
            ->and($link->externalEventId())->toBe('evt-1');
    });

    it('never mistakes a key on the row for a neighbour identity', function () {
        $link = $this->mapper->toEntity(($this->row)(), FakeBusinessContext::BUSINESS_ID);

        expect($link->connectionId)->not->toBe('7')
            ->and($link->appointmentId)->not->toBe('9')
            ->and($link->businessId)->not->toBe('42');
    });

    it('restores a link pointing nowhere without the invariant creation enforces', function () {
        expect($this->mapper->toEntity(($this->row)(['external_event_id' => '']), FakeBusinessContext::BUSINESS_ID)->externalEventId())
            ->toBe('');
    });
});

describe('writing a row', function () {
    it('writes exactly the columns the link owns, neighbours as the keys it was handed', function () {
        $link = CalendarEventLink::create(
            id: IntegrationsFixtures::LINK_ID,
            businessId: FakeBusinessContext::BUSINESS_ID,
            connectionId: IntegrationsFixtures::CONNECTION_ID,
            appointmentId: IntegrationsFixtures::APPOINTMENT_ID,
            externalEventId: 'evt-1',
        );

        expect($this->mapper->toAttributes($link, 42, 7, 9))->toBe([
            'uuid' => IntegrationsFixtures::LINK_ID,
            'business_id' => 42,
            'calendar_connection_id' => 7,
            'appointment_id' => 9,
            'external_event_id' => 'evt-1',
        ]);
    });
});
