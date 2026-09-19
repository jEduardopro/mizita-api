<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Exceptions\InvalidLeadTime;
use App\Domains\BookingPolicies\ValueObjects\LeadTime;
use App\Shared\ValueObjects\DomainFailureKind;

it('accepts a notice period inside the bounds the domain declares', function (int $minutes) {
    expect(LeadTime::ofMinutes($minutes)->minutes)->toBe($minutes);
})->with([
    'no notice at all' => LeadTime::MINIMUM_MINUTES,
    'one minute' => 1,
    'two hours' => 120,
    'thirty days' => LeadTime::MAXIMUM_MINUTES,
]);

it('refuses a notice period outside the bounds the domain declares', function (int $minutes) {
    expect(fn () => LeadTime::ofMinutes($minutes))->toThrow(InvalidLeadTime::class);
})->with([
    'one minute below zero' => -1,
    'one minute past the maximum' => LeadTime::MAXIMUM_MINUTES + 1,
    'a year' => 525600,
]);

it('refuses as a domain failure the responder can classify', function () {
    try {
        LeadTime::ofMinutes(-1);
        $thrown = null;
    } catch (InvalidLeadTime $refusal) {
        $thrown = $refusal;
    }

    expect($thrown?->errorCode())->toBe('invalid_lead_time')
        ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
});

it('restores whatever the column holds, skipping the rules that guard a new value', function () {
    expect(LeadTime::restore(-5)->minutes)->toBe(-5);
});

it('holds two equal notice periods to be the same', function () {
    expect(LeadTime::ofMinutes(60)->equals(LeadTime::ofMinutes(60)))->toBeTrue()
        ->and(LeadTime::ofMinutes(60)->equals(LeadTime::ofMinutes(61)))->toBeFalse();
});
