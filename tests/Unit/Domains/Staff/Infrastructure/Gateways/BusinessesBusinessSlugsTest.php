<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Staff\Infrastructure\Gateways\BusinessesBusinessSlugs;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeBusinessContext;

it('answers with the slug of the business named by its uuid', function () {
    $businesses = Mockery::mock(BusinessRepository::class);
    $businesses->shouldReceive('findById')->once()
        ->with(FakeBusinessContext::BUSINESS_ID)
        ->andReturn(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID, slug: 'barberia-nunoa'));

    expect((new BusinessesBusinessSlugs($businesses))->slugOf(FakeBusinessContext::BUSINESS_ID))->toBe('barberia-nunoa');
});

it('lets a business that is gone escape, so no link is built under a missing page', function () {
    $businesses = Mockery::mock(BusinessRepository::class);
    $businesses->shouldReceive('findById')->once()->andThrow(BusinessNotFound::withId(FakeBusinessContext::BUSINESS_ID));

    expect(fn () => (new BusinessesBusinessSlugs($businesses))->slugOf(FakeBusinessContext::BUSINESS_ID))
        ->toThrow(BusinessNotFound::class);
});
