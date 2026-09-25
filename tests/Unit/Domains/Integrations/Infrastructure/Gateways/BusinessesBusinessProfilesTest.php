<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Integrations\Exceptions\CalendarBusinessNotFound;
use App\Domains\Integrations\Infrastructure\Gateways\BusinessesBusinessProfiles;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->profiles = new BusinessesBusinessProfiles($this->businesses);
});

it('describes the business by its name and its IANA timezone', function () {
    $this->businesses->shouldReceive('findById')->once()
        ->with(FakeBusinessContext::BUSINESS_ID)
        ->andReturn(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID, timezone: 'America/Argentina/Buenos_Aires'));

    $profile = $this->profiles->profileOf(FakeBusinessContext::BUSINESS_ID);

    expect($profile->name)->toBe(OnboardingFixtures::NAME)
        ->and($profile->timezone)->toBe('America/Argentina/Buenos_Aires');
});

it('translates a missing business into its own domain failure', function () {
    $missing = BusinessNotFound::withId(FakeBusinessContext::BUSINESS_ID);
    $this->businesses->shouldReceive('findById')->once()->andThrow($missing);

    $failure = null;

    try {
        $this->profiles->profileOf(FakeBusinessContext::BUSINESS_ID);
    } catch (CalendarBusinessNotFound $translated) {
        $failure = $translated;
    }

    expect($failure)->toBeInstanceOf(CalendarBusinessNotFound::class)
        ->and($failure?->errorCode())->toBe('business_not_found')
        ->and($failure?->kind())->toBe(DomainFailureKind::NotFound)
        ->and($failure?->getPrevious())->toBe($missing);
});
