<?php

declare(strict_types=1);

use App\Domains\Availability\Exceptions\InvalidTimeOfDay;
use App\Domains\Availability\Exceptions\InvalidWeekday;
use App\Domains\Availability\Exceptions\OverlappingScheduleIntervals;
use App\Domains\Availability\Exceptions\ScheduleIntervalInverted;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

/**
 * @return array<string, array{DomainFailure, string, DomainFailureKind}>
 */
function availabilityFailures(): array
{
    return [
        'a malformed time' => [
            InvalidTimeOfDay::malformed('noon'),
            'invalid_time_of_day',
            DomainFailureKind::Invalid,
        ],
        'a time past the last hour of the day' => [
            InvalidTimeOfDay::outOfRange('25:00'),
            'invalid_time_of_day',
            DomainFailureKind::Invalid,
        ],
        'a number no weekday carries' => [
            InvalidWeekday::withNumber(8),
            'invalid_weekday',
            DomainFailureKind::Invalid,
        ],
        'two intervals colliding' => [
            OverlappingScheduleIntervals::onWeekday(1),
            'overlapping_schedule_intervals',
            DomainFailureKind::Conflict,
        ],
        'an inverted interval' => [
            ScheduleIntervalInverted::between('18:00', '09:00'),
            'schedule_interval_inverted',
            DomainFailureKind::Invalid,
        ],
    ];
}

it('answers with the stable error code the client is shown a sentence for', function (
    DomainFailure $failure,
    string $code,
) {
    expect($failure->errorCode())->toBe($code);
})->with(availabilityFailures());

it('classifies the refusal so the edge knows which status to render', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
) {
    expect($failure->kind())->toBe($kind);
})->with(availabilityFailures());

it('carries the interface the renderer is registered against', function (DomainFailure $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure)->toBeInstanceOf(Throwable::class);
})->with(availabilityFailures());

it('has a sentence to show the caller in every locale', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
    string $locale,
) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][$code] ?? '')->toBeString()->not->toBe('');
})->with(availabilityFailures())->with(['en', 'es']);

it('says what it turned down and nothing more', function () {
    expect(InvalidTimeOfDay::malformed('noon')->getMessage())
        ->toBe('[noon] is not a time of day written as HH:MM.')
        ->and(InvalidTimeOfDay::outOfRange('25:00')->getMessage())
        ->toBe('[25:00] is outside the hours a day holds.')
        ->and(InvalidWeekday::withNumber(8)->getMessage())
        ->toBe('[8] is not a day of the week.')
        ->and(OverlappingScheduleIntervals::onWeekday(3)->getMessage())
        ->toBe('Two schedule intervals overlap on weekday [3].')
        ->and(ScheduleIntervalInverted::between('18:00', '09:00')->getMessage())
        ->toBe('A schedule interval must end after it starts, got [18:00] to [09:00].');
});

it('gives each refusal an error code of its own', function () {
    $codes = [
        InvalidTimeOfDay::malformed('noon')->errorCode(),
        InvalidWeekday::withNumber(8)->errorCode(),
        OverlappingScheduleIntervals::onWeekday(1)->errorCode(),
        ScheduleIntervalInverted::between('18:00', '09:00')->errorCode(),
    ];

    expect(array_unique($codes))->toHaveCount(count($codes));
});
