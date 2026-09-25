<?php

declare(strict_types=1);

use App\Domains\Integrations\Events\CalendarDisconnected;
use App\Domains\Integrations\Infrastructure\Listeners\QueueCalendarCleanUp;
use App\Domains\Integrations\Infrastructure\Queue\CleanUpDisconnectedCalendar;
use Illuminate\Contracts\Bus\Dispatcher;
use Tests\Support\FakeBusinessContext;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

it('queues the clean up of the disconnected calendar in its business', function () {
    $bus = Mockery::mock(Dispatcher::class);
    $bus->shouldReceive('dispatch')->once()->with(Mockery::on(
        fn (mixed $job): bool => $job instanceof CleanUpDisconnectedCalendar
            && $job->businessId === FakeBusinessContext::BUSINESS_ID
            && $job->connectionId === IntegrationsFixtures::CONNECTION_ID,
    ));

    (new QueueCalendarCleanUp($bus))->handle(new CalendarDisconnected(
        connectionId: IntegrationsFixtures::CONNECTION_ID,
        businessId: FakeBusinessContext::BUSINESS_ID,
    ));
});
