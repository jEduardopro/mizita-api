<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\UseCases\ResumeBusinessCalendarSync;
use App\Domains\Integrations\Infrastructure\Listeners\QueueCalendarResync;
use App\Domains\Subscriptions\Events\SubscriptionStarted;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarBackfillQueue;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;

const QUEUE_CALENDAR_RESYNC_SUBSCRIPTION_ID = '01930000-0000-7000-8000-000000000201';

beforeEach(function () {
    $this->connections = (new FakeCalendarConnectionRepository)->store(
        IntegrationsFixtures::connection(),
        IntegrationsFixtures::connection(
            id: IntegrationsFixtures::SECOND_CONNECTION_ID,
            businessId: IntegrationsFixtures::OTHER_BUSINESS_ID,
        ),
    );
    $this->backfills = new FakeCalendarBackfillQueue;

    $this->listener = new QueueCalendarResync(new ResumeBusinessCalendarSync($this->connections, $this->backfills));
});

it('resumes the calendar sync of the business whose subscription started', function () {
    $this->listener->handle(new SubscriptionStarted(QUEUE_CALENDAR_RESYNC_SUBSCRIPTION_ID, IntegrationsFixtures::BUSINESS_ID));

    expect($this->connections->liveLookups)->toBe([IntegrationsFixtures::BUSINESS_ID])
        ->and($this->backfills->scheduled)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'connectionId' => IntegrationsFixtures::CONNECTION_ID,
        ]]);
});

it('hands the use case the business uuid of the event, not the subscription uuid', function () {
    $this->listener->handle(new SubscriptionStarted(QUEUE_CALENDAR_RESYNC_SUBSCRIPTION_ID, IntegrationsFixtures::OTHER_BUSINESS_ID));

    expect($this->connections->liveLookups)->toBe([IntegrationsFixtures::OTHER_BUSINESS_ID])
        ->and(array_column($this->backfills->scheduled, 'connectionId'))->toBe([IntegrationsFixtures::SECOND_CONNECTION_ID]);
});
