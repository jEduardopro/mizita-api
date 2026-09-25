<?php

declare(strict_types=1);

use App\Domains\Appointments\Events\AppointmentBooked;
use App\Domains\Appointments\Events\AppointmentCancelled;
use App\Domains\Appointments\Events\AppointmentCreated;
use App\Domains\Appointments\Events\AppointmentDeleted;
use App\Domains\Appointments\Events\AppointmentRescheduled;
use App\Domains\Appointments\Events\AppointmentUpdated;
use App\Domains\Integrations\Contracts\AppointmentSyncQueue;
use App\Domains\Integrations\Infrastructure\Listeners\QueueAppointmentCalendarSync;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

it('schedules a calendar sync of exactly the appointment that changed', function (string $eventClass) {
    $syncQueue = Mockery::mock(AppointmentSyncQueue::class);
    $syncQueue->shouldReceive('schedule')->once()->with(IntegrationsFixtures::APPOINTMENT_ID);

    (new QueueAppointmentCalendarSync($syncQueue))->handle(new $eventClass(IntegrationsFixtures::APPOINTMENT_ID));
})->with([
    'created' => [AppointmentCreated::class],
    'booked' => [AppointmentBooked::class],
    'updated' => [AppointmentUpdated::class],
    'rescheduled' => [AppointmentRescheduled::class],
    'cancelled' => [AppointmentCancelled::class],
    'deleted' => [AppointmentDeleted::class],
]);
