<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\ResumeBusinessCalendarSyncInput;
use App\Domains\Integrations\Application\UseCases\ResumeBusinessCalendarSync;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarBackfillQueue;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;

beforeEach(function () {
    $this->connections = new FakeCalendarConnectionRepository;
    $this->backfills = new FakeCalendarBackfillQueue;

    $this->resume = fn (string $businessId = IntegrationsFixtures::BUSINESS_ID) => (new ResumeBusinessCalendarSync(
        $this->connections,
        $this->backfills,
    ))->handle(new ResumeBusinessCalendarSyncInput($businessId));
});

describe('a business with live calendar connections', function () {
    beforeEach(function () {
        $this->connections->store(
            IntegrationsFixtures::connection(),
            IntegrationsFixtures::connection(
                id: IntegrationsFixtures::SECOND_CONNECTION_ID,
                staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
            ),
        );
    });

    it('schedules one backfill per live connection, carrying the business and connection uuids', function () {
        ($this->resume)();

        expect($this->backfills->scheduled)->toBe([
            ['businessId' => IntegrationsFixtures::BUSINESS_ID, 'connectionId' => IntegrationsFixtures::CONNECTION_ID],
            ['businessId' => IntegrationsFixtures::BUSINESS_ID, 'connectionId' => IntegrationsFixtures::SECOND_CONNECTION_ID],
        ]);
    });

    it('answers with how many backfills it scheduled', function () {
        $response = ($this->resume)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe(2);
    });

    it('asks for the live connections of the business of the input, once', function () {
        ($this->resume)();

        expect($this->connections->liveLookups)->toBe([IntegrationsFixtures::BUSINESS_ID]);
    });

    it('schedules nothing for the connections of another business', function () {
        $this->connections->store(IntegrationsFixtures::connection(
            id: IntegrationsFixtures::NEW_CONNECTION_ID,
            businessId: IntegrationsFixtures::OTHER_BUSINESS_ID,
        ));

        ($this->resume)();

        expect(array_column($this->backfills->scheduled, 'connectionId'))->toBe([
            IntegrationsFixtures::CONNECTION_ID,
            IntegrationsFixtures::SECOND_CONNECTION_ID,
        ])->and(array_unique(array_column($this->backfills->scheduled, 'businessId')))->toBe([IntegrationsFixtures::BUSINESS_ID]);
    });
});

describe('a business with no live calendar connection', function () {
    it('schedules nothing and answers zero', function () {
        $this->connections->store(IntegrationsFixtures::connection(businessId: IntegrationsFixtures::OTHER_BUSINESS_ID));

        $response = ($this->resume)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe(0)
            ->and($this->backfills->scheduled)->toBe([]);
    });
});
