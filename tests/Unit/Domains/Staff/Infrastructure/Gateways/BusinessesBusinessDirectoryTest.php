<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Staff\Infrastructure\Gateways\BusinessesBusinessDirectory;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeBusinessContext;

it('names the business by the uuid it was handed', function () {
    $businesses = Mockery::mock(BusinessRepository::class);
    $businesses->shouldReceive('findById')->once()
        ->with(FakeBusinessContext::BUSINESS_ID)
        ->andReturn(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID, name: 'Barbería Ñuñoa'));

    expect((new BusinessesBusinessDirectory($businesses))->nameOf(FakeBusinessContext::BUSINESS_ID))->toBe('Barbería Ñuñoa');
});

it('lets a business that is gone escape, so the invitation fails loudly', function () {
    $businesses = Mockery::mock(BusinessRepository::class);
    $businesses->shouldReceive('findById')->once()->andThrow(BusinessNotFound::withId(FakeBusinessContext::BUSINESS_ID));

    expect(fn () => (new BusinessesBusinessDirectory($businesses))->nameOf(FakeBusinessContext::BUSINESS_ID))
        ->toThrow(BusinessNotFound::class);
});
