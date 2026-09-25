<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Queue\QueuedAppointmentSync;
use App\Domains\Integrations\Infrastructure\Queue\SyncAppointmentToCalendar;
use Illuminate\Contracts\Bus\Dispatcher;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

it('dispatches one sync job for exactly the appointment it was handed', function () {
    $bus = Mockery::mock(Dispatcher::class);
    $bus->shouldReceive('dispatch')->once()->with(Mockery::on(
        fn (mixed $job): bool => $job instanceof SyncAppointmentToCalendar
            && $job->appointmentId === IntegrationsFixtures::APPOINTMENT_ID,
    ));

    (new QueuedAppointmentSync($bus))->schedule(IntegrationsFixtures::APPOINTMENT_ID);
});
