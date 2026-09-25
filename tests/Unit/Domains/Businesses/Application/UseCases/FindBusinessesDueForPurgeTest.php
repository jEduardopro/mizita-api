<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\UseCases\FindBusinessesDueForPurge;
use Tests\Support\Businesses\ClosureFixtures;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;

const DUE_FOR_PURGE_OLDEST_ID = '01930000-0000-7000-8000-000000000011';

const DUE_FOR_PURGE_AT_CUTOFF_ID = '01930000-0000-7000-8000-000000000012';

const DUE_FOR_PURGE_TOO_RECENT_ID = '01930000-0000-7000-8000-000000000013';

const DUE_FOR_PURGE_ALREADY_PURGED_ID = '01930000-0000-7000-8000-000000000014';

const DUE_FOR_PURGE_OPEN_ID = '01930000-0000-7000-8000-000000000015';

beforeEach(function () {
    $this->businesses = new FakeBusinessRepository;
    $this->clock = new FakeClock(ClosureFixtures::purgeDueAt());

    $this->useCase = new FindBusinessesDueForPurge($this->businesses, $this->clock);
});

it('asks for businesses closed no later than thirty days before the clock instant', function () {
    $this->useCase->handle();

    expect($this->businesses->purgeCutoffs)->toEqual([ClosureFixtures::closedAt()]);
});

it('moves the cutoff with the clock, never with real time', function () {
    $this->clock->advance('PT6H');

    $this->useCase->handle();

    expect($this->businesses->purgeCutoffs)->toEqual([new DateTimeImmutable('2026-02-01T16:00:00+00:00')]);
});

it('answers with the uuids of the businesses the repository reports as due, in its order', function () {
    $this->businesses->store(
        ClosureFixtures::closedBusiness(id: DUE_FOR_PURGE_AT_CUTOFF_ID, name: 'At the cutoff'),
        ClosureFixtures::closedBusiness(id: DUE_FOR_PURGE_OLDEST_ID, name: 'Oldest', closedAt: '2026-01-02T10:00:00+00:00'),
        ClosureFixtures::closedBusiness(id: DUE_FOR_PURGE_TOO_RECENT_ID, name: 'Too recent', closedAt: '2026-02-01T10:00:01+00:00'),
        ClosureFixtures::purgedBusiness(id: DUE_FOR_PURGE_ALREADY_PURGED_ID),
        OnboardingFixtures::business(id: DUE_FOR_PURGE_OPEN_ID, name: 'Still open'),
    );

    $response = $this->useCase->handle();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBe([DUE_FOR_PURGE_OLDEST_ID, DUE_FOR_PURGE_AT_CUTOFF_ID]);
});

it('answers with an empty list when nothing is due', function () {
    $this->businesses->store(OnboardingFixtures::business(id: DUE_FOR_PURGE_OPEN_ID));

    expect($this->useCase->handle()->value())->toBe([]);
});
