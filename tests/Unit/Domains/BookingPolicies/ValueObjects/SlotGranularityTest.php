<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Exceptions\InvalidSlotGranularity;
use App\Domains\BookingPolicies\ValueObjects\SlotGranularity;
use App\Shared\ValueObjects\DomainFailureKind;

it('accepts a step inside the bounds the domain declares', function (int $minutes) {
    expect(SlotGranularity::ofMinutes($minutes)->minutes)->toBe($minutes);
})->with([
    'five minutes' => SlotGranularity::MINIMUM_MINUTES,
    'a quarter of an hour' => 15,
    'half an hour' => 30,
    'an hour' => SlotGranularity::MAXIMUM_MINUTES,
]);

it('refuses a step outside the bounds the domain declares', function (int $minutes) {
    expect(fn () => SlotGranularity::ofMinutes($minutes))->toThrow(InvalidSlotGranularity::class);
})->with([
    'one minute below the minimum' => SlotGranularity::MINIMUM_MINUTES - 1,
    'no step at all' => 0,
    'a negative step' => -5,
    'one minute past the maximum' => SlotGranularity::MAXIMUM_MINUTES + 1,
    'two hours' => 120,
]);

it('refuses a step that does not land on the five minute grid', function (int $minutes) {
    expect(fn () => SlotGranularity::ofMinutes($minutes))->toThrow(InvalidSlotGranularity::class);
})->with([
    'seven' => 7,
    'eleven' => 11,
    'forty five and one' => 46,
    'fifty nine' => 59,
]);

it('refuses as a domain failure the responder can classify', function () {
    try {
        SlotGranularity::ofMinutes(7);
        $thrown = null;
    } catch (InvalidSlotGranularity $refusal) {
        $thrown = $refusal;
    }

    expect($thrown?->errorCode())->toBe('invalid_slot_granularity')
        ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
});

it('restores whatever the column holds, skipping the rules that guard a new value', function () {
    expect(SlotGranularity::restore(7)->minutes)->toBe(7);
});

it('holds two equal steps to be the same', function () {
    expect(SlotGranularity::ofMinutes(15)->equals(SlotGranularity::ofMinutes(15)))->toBeTrue()
        ->and(SlotGranularity::ofMinutes(15)->equals(SlotGranularity::ofMinutes(30)))->toBeFalse();
});
