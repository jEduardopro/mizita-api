<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Queue\BackfillCalendar;
use App\Domains\Integrations\Infrastructure\Queue\QueuedCalendarBackfill;
use Illuminate\Contracts\Bus\Dispatcher;
use Tests\Support\FakeBusinessContext;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

it('dispatches exactly one backfill job for the connection in its business', function () {
    $dispatched = [];
    $bus = Mockery::mock(Dispatcher::class);
    $bus->shouldReceive('dispatch')->andReturnUsing(function (object $job) use (&$dispatched): mixed {
        $dispatched[] = $job;

        return null;
    });

    (new QueuedCalendarBackfill($bus))->schedule(FakeBusinessContext::BUSINESS_ID, IntegrationsFixtures::CONNECTION_ID);

    expect($dispatched)->toHaveCount(1)
        ->and($dispatched[0])->toBeInstanceOf(BackfillCalendar::class)
        ->and($dispatched[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($dispatched[0]->connectionId)->toBe(IntegrationsFixtures::CONNECTION_ID);
});
