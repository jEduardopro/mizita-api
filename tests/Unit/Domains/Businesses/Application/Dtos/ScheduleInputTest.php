<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\ScheduleInput;
use App\Domains\Businesses\Exceptions\TooManyScheduleIntervals;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

function businessScheduleOf(int $entries): ScheduleInput
{
    return new ScheduleInput(array_fill(0, $entries, new BusinessScheduleEntry(1, '09:00', '10:00')));
}

it('caps a week at ten intervals a day', function () {
    expect(ScheduleInput::MAXIMUM_ENTRIES)->toBe(70);
});

it('accepts a schedule up to the cap', function (int $entries) {
    expect(fn () => businessScheduleOf($entries)->validate())->not->toThrow(Throwable::class);
})->with([
    'an empty week' => 0,
    'one interval' => 1,
    'exactly the cap' => 70,
]);

it('refuses a schedule past the cap', function (int $entries) {
    expect(fn () => businessScheduleOf($entries)->validate())->toThrow(TooManyScheduleIntervals::class);
})->with([
    'one past the cap' => 71,
    'far past the cap' => 10_000,
]);

it('refuses with an invalid domain failure under its own code', function () {
    try {
        businessScheduleOf(71)->validate();
    } catch (TooManyScheduleIntervals $failure) {
        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure->errorCode())->toBe('too_many_schedule_intervals')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

        return;
    }

    $this->fail('validate() accepted a week past the cap.');
});
