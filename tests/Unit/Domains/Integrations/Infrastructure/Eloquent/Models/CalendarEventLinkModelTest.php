<?php

declare(strict_types=1);

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarEventLinkModel;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Tests\TestCase;

uses(TestCase::class);

it('lives in the calendar event links table, routed by its uuid', function () {
    $link = new CalendarEventLinkModel;

    expect($link->getTable())->toBe('calendar_event_links')
        ->and($link->uniqueIds())->toBe(['uuid'])
        ->and($link->getRouteKeyName())->toBe('uuid');
});

it('still reads a connection that was soft deleted', function () {
    $connection = (new CalendarEventLinkModel)->connection();

    expect($connection->getRelated())->toBeInstanceOf(CalendarConnectionModel::class)
        ->and($connection->getForeignKeyName())->toBe('calendar_connection_id')
        ->and($connection->getQuery()->removedScopes())->toContain(SoftDeletingScope::class);
});

it('still reads an appointment that was soft deleted', function () {
    $appointment = (new CalendarEventLinkModel)->appointment();

    expect($appointment->getRelated())->toBeInstanceOf(AppointmentModel::class)
        ->and($appointment->getForeignKeyName())->toBe('appointment_id')
        ->and($appointment->getQuery()->removedScopes())->toContain(SoftDeletingScope::class);
});
