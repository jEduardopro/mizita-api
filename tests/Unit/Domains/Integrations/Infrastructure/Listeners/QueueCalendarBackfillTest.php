<?php

declare(strict_types=1);

use App\Domains\Integrations\Events\CalendarConnected;
use App\Domains\Integrations\Infrastructure\Listeners\QueueCalendarBackfill;
use App\Domains\Integrations\Infrastructure\Queue\BackfillCalendar;
use Illuminate\Contracts\Bus\Dispatcher;
use Tests\Support\FakeBusinessContext;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

it('queues the backfill of the connected calendar in its business', function () {
    $bus = Mockery::mock(Dispatcher::class);
    $bus->shouldReceive('dispatch')->once()->with(Mockery::on(
        fn (mixed $job): bool => $job instanceof BackfillCalendar
            && $job->businessId === FakeBusinessContext::BUSINESS_ID
            && $job->connectionId === IntegrationsFixtures::CONNECTION_ID,
    ));

    (new QueueCalendarBackfill($bus))->handle(new CalendarConnected(
        connectionId: IntegrationsFixtures::CONNECTION_ID,
        businessId: FakeBusinessContext::BUSINESS_ID,
    ));
});
