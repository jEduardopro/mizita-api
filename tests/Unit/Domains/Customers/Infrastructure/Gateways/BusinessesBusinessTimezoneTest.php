<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Customers\Contracts\BusinessTimezone;
use App\Domains\Customers\Infrastructure\Gateways\BusinessesBusinessTimezone;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\OnboardingFixtures;

beforeEach(function () {
    $this->businesses = new FakeBusinessRepository;
    $this->gateway = new BusinessesBusinessTimezone($this->businesses);
});

it('implements the port the customers domain declared', function () {
    expect($this->gateway)->toBeInstanceOf(BusinessTimezone::class);
});

it('answers the timezone the business keeps', function () {
    $this->businesses->store(OnboardingFixtures::business(timezone: 'America/Mexico_City'));

    expect($this->gateway->timezoneOf(OnboardingFixtures::GENERATED_BUSINESS_ID)->getName())
        ->toBe('America/Mexico_City');
});

it('reads the business it was asked about by its uuid', function () {
    $this->businesses->store(OnboardingFixtures::business());

    $this->gateway->timezoneOf(OnboardingFixtures::GENERATED_BUSINESS_ID);

    expect($this->businesses->idsRead)->toBe([OnboardingFixtures::GENERATED_BUSINESS_ID]);
});

it('lets a missing business surface as the not found failure the repository throws', function () {
    expect(fn () => $this->gateway->timezoneOf(OnboardingFixtures::GENERATED_BUSINESS_ID))
        ->toThrow(BusinessNotFound::class);
});
