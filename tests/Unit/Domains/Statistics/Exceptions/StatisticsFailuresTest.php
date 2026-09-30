<?php

declare(strict_types=1);

use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use App\Domains\Statistics\Exceptions\StatisticsPeriodTooWide;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

/**
 * @return array<string, array{DomainFailure, string, DomainFailureKind}>
 */
function statisticsFailures(): array
{
    return [
        'a malformed date' => [InvalidStatisticsPeriod::malformed('2026/09/01'), 'invalid_statistics_period', DomainFailureKind::Invalid],
        'only one end of the period' => [InvalidStatisticsPeriod::incomplete(), 'invalid_statistics_period', DomainFailureKind::Invalid],
        'an inverted period' => [InvalidStatisticsPeriod::inverted('2026-09-29', '2026-09-01'), 'invalid_statistics_period', DomainFailureKind::Invalid],
        'a period ending after today' => [InvalidStatisticsPeriod::endsAfterToday('2026-09-30', '2026-09-29'), 'invalid_statistics_period', DomainFailureKind::Invalid],
        'a period too wide' => [StatisticsPeriodTooWide::spanning(367, 366), 'statistics_period_too_wide', DomainFailureKind::Invalid],
    ];
}

it('answers with the stable error code the client is shown a sentence for', function (DomainFailure $failure, string $code) {
    expect($failure->errorCode())->toBe($code);
})->with(statisticsFailures());

it('classifies the refusal as invalid, which the edge renders as 422', function (DomainFailure $failure, string $code, DomainFailureKind $kind) {
    expect($failure->kind())->toBe($kind);
})->with(statisticsFailures());

it('carries the interface the renderer is registered against', function (DomainFailure $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure)->toBeInstanceOf(Throwable::class);
})->with(statisticsFailures());

it('has a sentence to show the caller in every locale', function (DomainFailure $failure, string $code, DomainFailureKind $kind, string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][$code] ?? '')->toBeString()->not->toBe('');
})->with(statisticsFailures())->with(['en', 'es']);

it('says what it turned down', function () {
    expect(InvalidStatisticsPeriod::malformed('2026/09/01')->getMessage())
        ->toBe('The statistics date [2026/09/01] is not a calendar date in the YYYY-MM-DD form.')
        ->and(InvalidStatisticsPeriod::inverted('2026-09-29', '2026-09-01')->getMessage())
        ->toBe('A statistics period has to start on or before it ends, got [2026-09-29] to [2026-09-01].')
        ->and(InvalidStatisticsPeriod::endsAfterToday('2026-09-30', '2026-09-29')->getMessage())
        ->toBe('A statistics period may not end after today [2026-09-29], got [2026-09-30].')
        ->and(StatisticsPeriodTooWide::spanning(367, 366)->getMessage())
        ->toBe('A statistics period may span at most [366] days, got [367].');
});

it('gives the too wide refusal a code of its own', function () {
    expect(StatisticsPeriodTooWide::spanning(367, 366)->errorCode())
        ->not->toBe(InvalidStatisticsPeriod::incomplete()->errorCode());
});
