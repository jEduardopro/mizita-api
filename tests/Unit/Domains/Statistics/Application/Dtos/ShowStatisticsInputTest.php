<?php

declare(strict_types=1);

use App\Domains\Statistics\Application\Dtos\ShowStatisticsInput;
use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use App\Domains\Statistics\Exceptions\StatisticsPeriodTooWide;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('no period at all', function () {
    it('is valid', function () {
        expect(fn () => (new ShowStatisticsInput(null, null))->validate())->not->toThrow(Throwable::class);
    });

    it('asks for no period, so the calendar picks the default', function () {
        expect((new ShowStatisticsInput(null, null))->toPeriod())->toBeNull();
    });

    it('is what a payload with no dates builds', function (array $payload) {
        $input = ShowStatisticsInput::fromRequest($payload);

        expect($input->from)->toBeNull()
            ->and($input->to)->toBeNull()
            ->and(fn () => $input->validate())->not->toThrow(Throwable::class);
    })->with([
        'no keys' => [[]],
        'both null' => [['from' => null, 'to' => null]],
        'both empty' => [['from' => '', 'to' => '']],
        'both whitespace' => [['from' => '  ', 'to' => "\t"]],
        'both not strings' => [['from' => 20260901, 'to' => ['2026-09-29']]],
    ]);
});

describe('a complete period', function () {
    it('is valid', function () {
        expect(fn () => (new ShowStatisticsInput('2026-09-01', '2026-09-29'))->validate())->not->toThrow(Throwable::class);
    });

    it('becomes the period between the two dates', function () {
        $period = ShowStatisticsInput::fromRequest(['from' => '2026-09-01', 'to' => '2026-09-29'])->toPeriod();

        expect($period?->from->toString())->toBe('2026-09-01')
            ->and($period?->to->toString())->toBe('2026-09-29');
    });

    it('accepts a single day', function () {
        expect(fn () => (new ShowStatisticsInput('2026-09-29', '2026-09-29'))->validate())->not->toThrow(Throwable::class);
    });

    it('accepts exactly 366 days', function () {
        expect(fn () => (new ShowStatisticsInput('2025-09-29', '2026-09-29'))->validate())->not->toThrow(Throwable::class);
    });

    it('leaves the end after today to the use case, which owns the clock', function () {
        expect(fn () => (new ShowStatisticsInput('2099-01-01', '2099-01-31'))->validate())->not->toThrow(Throwable::class);
    });
});

describe('a payload the form request would have rejected', function () {
    it('is refused with the right failure', function (ShowStatisticsInput $input, string $exception) {
        expect(fn () => $input->validate())->toThrow($exception);
    })->with('rejected statistics inputs');

    it('is refused with a failure the transport classifies as invalid', function (ShowStatisticsInput $input, string $exception, string $code) {
        try {
            $input->validate();
        } catch (DomainFailure $failure) {
            expect($failure)->toBeInstanceOf($exception)
                ->and($failure->errorCode())->toBe($code)
                ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

            return;
        }

        $this->fail('validate() accepted a payload it should have refused.');
    })->with('rejected statistics inputs');
});

dataset('rejected statistics inputs', fn () => [
    'only from' => [new ShowStatisticsInput('2026-09-01', null), InvalidStatisticsPeriod::class, 'invalid_statistics_period'],
    'only to' => [new ShowStatisticsInput(null, '2026-09-29'), InvalidStatisticsPeriod::class, 'invalid_statistics_period'],
    'only from, through the payload' => [
        ShowStatisticsInput::fromRequest(['from' => '2026-09-01']),
        InvalidStatisticsPeriod::class,
        'invalid_statistics_period',
    ],
    'only to, the from left blank' => [
        ShowStatisticsInput::fromRequest(['from' => '   ', 'to' => '2026-09-29']),
        InvalidStatisticsPeriod::class,
        'invalid_statistics_period',
    ],
    'a malformed from' => [new ShowStatisticsInput('2026/09/01', '2026-09-29'), InvalidStatisticsPeriod::class, 'invalid_statistics_period'],
    'a malformed to' => [new ShowStatisticsInput('2026-09-01', '29-09-2026'), InvalidStatisticsPeriod::class, 'invalid_statistics_period'],
    'a date the calendar lacks' => [new ShowStatisticsInput('2026-02-29', '2026-03-10'), InvalidStatisticsPeriod::class, 'invalid_statistics_period'],
    'an empty from built directly' => [new ShowStatisticsInput('', '2026-09-29'), InvalidStatisticsPeriod::class, 'invalid_statistics_period'],
    'an inverted range' => [new ShowStatisticsInput('2026-09-29', '2026-09-01'), InvalidStatisticsPeriod::class, 'invalid_statistics_period'],
    'a range of 367 days' => [new ShowStatisticsInput('2025-09-28', '2026-09-29'), StatisticsPeriodTooWide::class, 'statistics_period_too_wide'],
]);
