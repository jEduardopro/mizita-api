<?php

declare(strict_types=1);

use App\Domains\Statistics\Application\Dtos\AppointmentSummaryData;
use App\Domains\Statistics\Application\Dtos\BusinessStatisticsData;
use App\Domains\Statistics\Application\Dtos\CustomerSummaryData;
use App\Domains\Statistics\Application\Dtos\DailyCollectionData;
use App\Domains\Statistics\Application\Dtos\PaymentMethodShareData;
use App\Domains\Statistics\Application\Dtos\PeriodCollectionData;
use App\Domains\Statistics\Application\Dtos\ShowStatisticsInput;
use App\Domains\Statistics\Application\Dtos\StaffShareData;
use App\Domains\Statistics\Application\UseCases\ShowBusinessStatistics;
use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use App\Domains\Statistics\Exceptions\StatisticsPeriodTooWide;
use App\Domains\Statistics\Services\PercentageCalculator;
use App\Domains\Statistics\Services\StatisticsCalendar;
use App\Domains\Statistics\ValueObjects\AppointmentTally;
use App\Domains\Statistics\ValueObjects\CustomerTally;
use App\Domains\Statistics\ValueObjects\PaymentMethodCollection;
use App\Domains\Statistics\ValueObjects\StaffPerformance;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\Statistics\FakeBusinessCurrency;
use Tests\Support\Statistics\FakeBusinessTimezone;
use Tests\Support\Statistics\FakeStatisticsReader;
use Tests\Support\Statistics\WindowSpan;

beforeEach(function () {
    $this->ownerStaffId = '01930000-0000-7000-8000-0000000000d1';
    $this->colleagueStaffId = '01930000-0000-7000-8000-0000000000d2';
    $this->noonInMonterrey = new DateTimeImmutable('2026-09-29T18:00:00+00:00');

    $this->reader = new FakeStatisticsReader;
    $this->timezones = new FakeBusinessTimezone;
    $this->currencies = new FakeBusinessCurrency;
    $this->business = new FakeBusinessContext;
    $this->clock = new FakeClock($this->noonInMonterrey);

    $this->show = fn (?string $from = null, ?string $to = null) => (new ShowBusinessStatistics(
        $this->reader,
        $this->timezones,
        $this->currencies,
        $this->business,
        $this->clock,
        new StatisticsCalendar,
        new PercentageCalculator,
    ))->handle(new ShowStatisticsInput($from, $to));
});

describe('the default report', function () {
    beforeEach(function () {
        $this->reader
            ->collectsBetween('2026-09-01', '2026-09-29', 28300)
            ->collectsBetween('2026-08-01', '2026-08-29', 30000)
            ->collectsBetween('2026-09-29', '2026-09-29', 4500);
    });

    it('answers with the statistics of the business', function () {
        expect(($this->show)()->value())->toBeInstanceOf(BusinessStatisticsData::class);
    });

    it('names the currency and the timezone of the business', function () {
        $data = ($this->show)()->value();

        expect($data->currencyCode)->toBe('MXN')
            ->and($data->timezone)->toBe('America/Monterrey');
    });

    it('reports month to date against the same dates a month earlier', function () {
        $data = ($this->show)()->value();

        expect($data->period)->toEqual(new PeriodCollectionData('2026-09-01', '2026-09-29', 28300))
            ->and($data->previousPeriod)->toEqual(new PeriodCollectionData('2026-08-01', '2026-08-29', 30000));
    });

    it('computes the change against the previous period', function () {
        expect(($this->show)()->value()->changePercent)->toBe(-5.67);
    });

    it('reports what was collected today on the business wall clock', function () {
        expect(($this->show)()->value()->today)->toEqual(new DailyCollectionData('2026-09-29', 4500));
    });
});

describe('the change against the previous period', function () {
    it('is null when the previous period collected nothing', function () {
        $this->reader->collectsBetween('2026-09-01', '2026-09-29', 50000);

        $data = ($this->show)()->value();

        expect($data->previousPeriod->collectedCents)->toBe(0)
            ->and($data->changePercent)->toBeNull();
    });

    it('is null when neither period collected anything', function () {
        expect(($this->show)()->value()->changePercent)->toBeNull();
    });

    it('is minus one hundred when this period collected nothing', function () {
        $this->reader->collectsBetween('2026-08-01', '2026-08-29', 30000);

        expect(($this->show)()->value()->changePercent)->toBe(-100.0);
    });
});

describe('the last seven days', function () {
    it('lists seven days ending today, filling the days with no money with zero', function () {
        $this->reader->collectsPerDay(['2026-09-24' => 1500, '2026-09-29' => 800]);

        expect(($this->show)()->value()->lastSevenDays)->toEqual([
            new DailyCollectionData('2026-09-23', 0),
            new DailyCollectionData('2026-09-24', 1500),
            new DailyCollectionData('2026-09-25', 0),
            new DailyCollectionData('2026-09-26', 0),
            new DailyCollectionData('2026-09-27', 0),
            new DailyCollectionData('2026-09-28', 0),
            new DailyCollectionData('2026-09-29', 800),
        ]);
    });

    it('lists seven zeros for a week with no money at all', function () {
        $days = ($this->show)()->value()->lastSevenDays;

        expect($days)->toHaveCount(7)
            ->and(array_column($days, 'collectedCents'))->toBe([0, 0, 0, 0, 0, 0, 0]);
    });

    it('keeps a day that netted negative', function () {
        $this->reader->collectsPerDay(['2026-09-27' => -2000]);

        expect(($this->show)()->value()->lastSevenDays[4])->toEqual(new DailyCollectionData('2026-09-27', -2000));
    });

    it('drops a day the reader answers with outside the week', function () {
        $this->reader->collectsPerDay(['2026-09-22' => 9900, '2026-09-30' => 9900]);

        $days = ($this->show)()->value()->lastSevenDays;

        expect(array_column($days, 'date'))->toBe([
            '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26', '2026-09-27', '2026-09-28', '2026-09-29',
        ])->and(array_sum(array_column($days, 'collectedCents')))->toBe(0);
    });

    it('ignores the requested period', function () {
        expect(array_column(($this->show)('2026-07-01', '2026-07-31')->value()->lastSevenDays, 'date'))->toBe([
            '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26', '2026-09-27', '2026-09-28', '2026-09-29',
        ]);
    });
});

describe('the money by payment method', function () {
    it('shares each method against the period total, in the order the reader ranked them', function () {
        $this->reader
            ->collectsBetween('2026-09-01', '2026-09-29', 10000)
            ->collectsThrough(
                new PaymentMethodCollection('cash', 7060),
                new PaymentMethodCollection('bank_transfer', 2940),
            );

        expect(($this->show)()->value()->byPaymentMethod)->toEqual([
            new PaymentMethodShareData('cash', 7060, 70.6),
            new PaymentMethodShareData('bank_transfer', 2940, 29.4),
        ]);
    });

    it('shares against the net total, so a method that refunded more than it took goes negative', function () {
        $this->reader
            ->collectsBetween('2026-09-01', '2026-09-29', 10000)
            ->collectsThrough(
                new PaymentMethodCollection('cash', 12000),
                new PaymentMethodCollection('card', -2000),
            );

        expect(array_column(($this->show)()->value()->byPaymentMethod, 'sharePercent'))->toBe([120.0, -20.0]);
    });

    it('gives every method a zero share when the period nets to nothing', function () {
        $this->reader->collectsThrough(new PaymentMethodCollection('cash', 0));

        expect(($this->show)()->value()->byPaymentMethod)->toEqual([new PaymentMethodShareData('cash', 0, 0.0)]);
    });

    it('lists no method when nothing moved', function () {
        expect(($this->show)()->value()->byPaymentMethod)->toBe([]);
    });
});

describe('the money by staff member', function () {
    beforeEach(function () {
        $this->reader
            ->collectsBetween('2026-09-01', '2026-09-29', 40000)
            ->performs(
                new StaffPerformance($this->ownerStaffId, 'Eduardo Facio', 'eduardo@example.com', 30000, 12),
                new StaffPerformance($this->colleagueStaffId, 'José Pablo Núñez', 'jose@example.com', 10000, 5),
            );
    });

    it('reports every staff member field by field, with a share of the period total', function () {
        expect(($this->show)()->value()->byStaff)->toEqual([
            new StaffShareData($this->ownerStaffId, 'Eduardo Facio', 'eduardo@example.com', 30000, 75.0, 12),
            new StaffShareData($this->colleagueStaffId, 'José Pablo Núñez', 'jose@example.com', 10000, 25.0, 5),
        ]);
    });

    it('identifies every staff member by uuid, never by a row number', function () {
        $ids = array_column(($this->show)()->value()->byStaff, 'id');

        expect($ids)->toBe([$this->ownerStaffId, $this->colleagueStaffId])
            ->and(array_filter($ids, is_numeric(...)))->toBe([]);
    });

    it('gives a zero share to a staff member who attended but collected nothing', function () {
        $this->reader->performs(new StaffPerformance($this->colleagueStaffId, 'José Pablo Núñez', 'jose@example.com', 0, 3));

        expect(($this->show)()->value()->byStaff)->toEqual([
            new StaffShareData($this->colleagueStaffId, 'José Pablo Núñez', 'jose@example.com', 0, 0.0, 3),
        ]);
    });
});

describe('the appointments', function () {
    it('reports the tally with a count for every booking source', function () {
        $this->reader->tallies(new AppointmentTally(
            total: 20,
            attended: 12,
            cancelled: 3,
            upcoming: 5,
            bookedBySource: ['admin' => 15, 'public' => 5],
        ));

        expect(($this->show)()->value()->appointments)->toEqual(
            new AppointmentSummaryData(20, 12, 3, 5, ['admin' => 15, 'public' => 5]),
        );
    });

    it('reports a source nobody booked through as zero rather than dropping it', function () {
        $this->reader->tallies(new AppointmentTally(
            total: 4,
            attended: 4,
            cancelled: 0,
            upcoming: 0,
            bookedBySource: ['admin' => 4],
        ));

        expect(($this->show)()->value()->appointments->bySource)->toBe(['admin' => 4, 'public' => 0]);
    });

    it('reports both sources as zero when there were no appointments', function () {
        expect(($this->show)()->value()->appointments)->toEqual(new AppointmentSummaryData(0, 0, 0, 0, ['admin' => 0, 'public' => 0]));
    });
});

describe('the customers', function () {
    it('reports the customers who are not new as returning', function () {
        $this->reader->counts(new CustomerTally(attended: 9, new: 4));

        expect(($this->show)()->value()->customers)->toEqual(new CustomerSummaryData(9, 4, 5));
    });

    it('reports no returning customers when every attended customer is new', function () {
        $this->reader->counts(new CustomerTally(attended: 3, new: 3));

        expect(($this->show)()->value()->customers)->toEqual(new CustomerSummaryData(3, 3, 0));
    });
});

describe('the windows handed to the reader', function () {
    it('asks for the period, the previous period and today, as UTC instants of local midnights', function () {
        ($this->show)();

        expect(array_map(
            static fn (array $call): array => WindowSpan::of($call['window']),
            $this->reader->callsTo('netCollectedCents'),
        ))->toBe([
            ['from' => '2026-09-01', 'to' => '2026-09-29', 'startsAt' => '2026-09-01T06:00:00+00:00', 'endsAt' => '2026-09-30T06:00:00+00:00'],
            ['from' => '2026-08-01', 'to' => '2026-08-29', 'startsAt' => '2026-08-01T06:00:00+00:00', 'endsAt' => '2026-08-30T06:00:00+00:00'],
            ['from' => '2026-09-29', 'to' => '2026-09-29', 'startsAt' => '2026-09-29T06:00:00+00:00', 'endsAt' => '2026-09-30T06:00:00+00:00'],
        ]);
    });

    it('asks for the last seven days bucketed in the business timezone', function () {
        ($this->show)();

        $calls = $this->reader->callsTo('netCollectedCentsPerLocalDay');

        expect($calls)->toHaveCount(1)
            ->and(WindowSpan::of($calls[0]['window']))->toBe([
                'from' => '2026-09-23',
                'to' => '2026-09-29',
                'startsAt' => '2026-09-23T06:00:00+00:00',
                'endsAt' => '2026-09-30T06:00:00+00:00',
            ])
            ->and($calls[0]['zone']?->getName())->toBe('America/Monterrey');
    });

    it('asks for every breakdown over the period window', function (string $method) {
        ($this->show)('2026-07-01', '2026-07-31');

        $calls = $this->reader->callsTo($method);

        expect($calls)->toHaveCount(1)
            ->and(WindowSpan::of($calls[0]['window']))->toBe([
                'from' => '2026-07-01',
                'to' => '2026-07-31',
                'startsAt' => '2026-07-01T06:00:00+00:00',
                'endsAt' => '2026-08-01T06:00:00+00:00',
            ]);
    })->with([
        'by payment method' => 'netCollectedByPaymentMethod',
        'by staff member' => 'staffPerformance',
        'appointments' => 'appointmentTally',
        'customers' => 'customerTally',
    ]);

    it('hands the clock reading to every breakdown that tells past from future', function (string $method) {
        ($this->show)();

        expect($this->reader->callsTo($method)[0]['now'])->toEqual($this->noonInMonterrey);
    })->with([
        'by staff member' => 'staffPerformance',
        'appointments' => 'appointmentTally',
        'customers' => 'customerTally',
    ]);

    it('compares a requested month ending on the 31st against the end of the shorter month before', function () {
        $this->clock = new FakeClock(new DateTimeImmutable('2026-04-10T18:00:00+00:00'));

        $data = ($this->show)('2026-03-01', '2026-03-31')->value();

        expect($data->period->from)->toBe('2026-03-01')
            ->and($data->period->to)->toBe('2026-03-31')
            ->and($data->previousPeriod->from)->toBe('2026-02-01')
            ->and($data->previousPeriod->to)->toBe('2026-02-28');
    });

    it('reports today as the local date when UTC has already reached the next one', function () {
        $this->clock = new FakeClock(new DateTimeImmutable('2026-09-30T05:30:00+00:00'));

        $data = ($this->show)()->value();

        expect($data->today->date)->toBe('2026-09-29')
            ->and($data->period->to)->toBe('2026-09-29')
            ->and($data->lastSevenDays[6]->date)->toBe('2026-09-29');
    });

    it('converts each date on its own offset for a business across a daylight saving change', function () {
        $this->timezones = new FakeBusinessTimezone('Europe/Madrid');
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-26T11:00:00+00:00'));

        ($this->show)();

        $calls = $this->reader->callsTo('netCollectedCentsPerLocalDay');

        expect(WindowSpan::of($calls[0]['window']))->toBe([
            'from' => '2026-10-20',
            'to' => '2026-10-26',
            'startsAt' => '2026-10-19T22:00:00+00:00',
            'endsAt' => '2026-10-26T23:00:00+00:00',
        ])->and($calls[0]['zone']?->getName())->toBe('Europe/Madrid');
    });
});

describe('the business the statistics are read for', function () {
    beforeEach(function () {
        $this->otherBusinessId = '01930000-0000-7000-8000-0000000000b2';
        $this->business = new FakeBusinessContext($this->otherBusinessId);
    });

    it('asks the reader about the business in context on every call', function () {
        ($this->show)();

        expect($this->reader->calls)->toHaveCount(8)
            ->and(array_unique(array_column($this->reader->calls, 'businessId')))->toBe([$this->otherBusinessId]);
    });

    it('reads the timezone and the currency of the business in context', function () {
        ($this->show)();

        expect($this->timezones->reads)->toBe([$this->otherBusinessId])
            ->and($this->currencies->reads)->toBe([$this->otherBusinessId]);
    });

    it('reports in the timezone and the currency that business filed', function () {
        $this->timezones = new FakeBusinessTimezone('Europe/Madrid');
        $this->currencies = new FakeBusinessCurrency('EUR');

        $data = ($this->show)()->value();

        expect($data->timezone)->toBe('Europe/Madrid')
            ->and($data->currencyCode)->toBe('EUR');
    });
});

describe('a period the use case refuses', function () {
    it('answers with the failure the edge renders as 422', function (?string $from, ?string $to, string $code, string $exception) {
        $response = ($this->show)($from, $to);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause())->toBeInstanceOf($exception);
    })->with('refused statistics periods');

    it('reads no statistics at all', function (?string $from, ?string $to) {
        ($this->show)($from, $to);

        expect($this->reader->calls)->toBe([]);
    })->with('refused statistics periods');

    it('turns a malformed payload down before looking the business up', function (?string $from, ?string $to) {
        ($this->show)($from, $to);

        expect($this->timezones->reads)->toBe([])
            ->and($this->currencies->reads)->toBe([]);
    })->with([
        'only from' => ['2026-09-01', null],
        'malformed' => ['2026/09/01', '2026-09-29'],
        'inverted' => ['2026-09-29', '2026-09-01'],
        'too wide' => ['2025-09-28', '2026-09-29'],
    ]);

    it('refuses a period ending tomorrow on the business wall clock although UTC is already there', function () {
        $this->clock = new FakeClock(new DateTimeImmutable('2026-09-30T03:00:00+00:00'));

        $response = ($this->show)('2026-09-01', '2026-09-30');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_statistics_period')
            ->and($this->reader->calls)->toBe([]);
    });

    it('accepts a period ending today', function () {
        expect(($this->show)('2026-09-01', '2026-09-29')->succeeded())->toBeTrue();
    });

    it('accepts the widest period allowed', function () {
        expect(($this->show)('2025-09-29', '2026-09-29')->succeeded())->toBeTrue();
    });
});

dataset('refused statistics periods', [
    'only from' => ['2026-09-01', null, 'invalid_statistics_period', InvalidStatisticsPeriod::class],
    'only to' => [null, '2026-09-29', 'invalid_statistics_period', InvalidStatisticsPeriod::class],
    'a malformed from' => ['2026/09/01', '2026-09-29', 'invalid_statistics_period', InvalidStatisticsPeriod::class],
    'a malformed to' => ['2026-09-01', 'yesterday', 'invalid_statistics_period', InvalidStatisticsPeriod::class],
    'inverted' => ['2026-09-29', '2026-09-01', 'invalid_statistics_period', InvalidStatisticsPeriod::class],
    'too wide' => ['2025-09-28', '2026-09-29', 'statistics_period_too_wide', StatisticsPeriodTooWide::class],
    'ending tomorrow' => ['2026-09-01', '2026-09-30', 'invalid_statistics_period', InvalidStatisticsPeriod::class],
]);
