<?php

declare(strict_types=1);

use App\Domains\Payments\Contracts\BusinessTimezone;
use App\Domains\Payments\Exceptions\PaymentBusinessNotFound;
use App\Domains\Payments\Infrastructure\Gateways\BusinessesBusinessTimezone;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\OnboardingFixtures;

beforeEach(function () {
    $this->businesses = new FakeBusinessRepository;
    $this->gateway = new BusinessesBusinessTimezone($this->businesses);
});

it('implements the port the payments domain declared', function () {
    expect($this->gateway)->toBeInstanceOf(BusinessTimezone::class);
});

it('answers the timezone the business keeps', function () {
    $this->businesses->store(OnboardingFixtures::business(timezone: 'America/New_York'));

    expect($this->gateway->timezoneOf(OnboardingFixtures::GENERATED_BUSINESS_ID)->getName())
        ->toBe('America/New_York');
});

it('reads the business it was asked about by its uuid', function () {
    $this->businesses->store(OnboardingFixtures::business());

    $this->gateway->timezoneOf(OnboardingFixtures::GENERATED_BUSINESS_ID);

    expect($this->businesses->idsRead)->toBe([OnboardingFixtures::GENERATED_BUSINESS_ID]);
});

it('translates a missing business into the refusal of the payments domain', function () {
    expect(fn () => $this->gateway->timezoneOf(OnboardingFixtures::GENERATED_BUSINESS_ID))
        ->toThrow(PaymentBusinessNotFound::class, 'Business ['.OnboardingFixtures::GENERATED_BUSINESS_ID.'] was not found.');
});
