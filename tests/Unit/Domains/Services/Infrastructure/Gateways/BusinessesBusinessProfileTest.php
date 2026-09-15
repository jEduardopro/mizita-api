<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Services\Infrastructure\Gateways\BusinessesBusinessProfile;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->profile = new BusinessesBusinessProfile($this->businesses);
});

it('answers with the address of the business it was asked about', function () {
    $this->businesses->shouldReceive('findById')->once()
        ->with(FakeBusinessContext::BUSINESS_ID)
        ->andReturn(OnboardingFixtures::business(slug: 'ada-salon'));

    expect($this->profile->slugFor(FakeBusinessContext::BUSINESS_ID))->toBe('ada-salon');
});

it('lets a missing business surface as the domain failure the neighbour threw', function () {
    $this->businesses->shouldReceive('findById')->once()
        ->andThrow(BusinessNotFound::withId(FakeBusinessContext::BUSINESS_ID));

    expect(fn () => $this->profile->slugFor(FakeBusinessContext::BUSINESS_ID))
        ->toThrow(BusinessNotFound::class)
        ->and(BusinessNotFound::withId('x'))->toBeInstanceOf(DomainFailure::class);
});
