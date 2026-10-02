<?php

declare(strict_types=1);

use App\Domains\Statistics\Contracts\StatisticsReader;
use App\Domains\Statistics\Infrastructure\Gateways\CachedStatisticsReader;
use App\Domains\Statistics\ValueObjects\AppointmentTally;
use App\Domains\Statistics\ValueObjects\BookingSource;
use App\Domains\Statistics\ValueObjects\CustomerTally;
use App\Domains\Statistics\ValueObjects\LocalDate;
use App\Domains\Statistics\ValueObjects\PaymentMethodCollection;
use App\Domains\Statistics\ValueObjects\ReportingWindow;
use App\Domains\Statistics\ValueObjects\StaffPerformance;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Tests\Support\Statistics\FakeStatisticsReader;

const CACHED_STATISTICS_BUSINESS_ID = '01930000-0000-7000-8000-00000000b001';

const CACHED_STATISTICS_OTHER_BUSINESS_ID = '01930000-0000-7000-8000-00000000b002';

const CACHED_STATISTICS_TTL_SECONDS = 60;

function septemberInMadrid(): ReportingWindow
{
    return new ReportingWindow(
        LocalDate::fromString('2026-09-01'),
        LocalDate::fromString('2026-09-30'),
        new DateTimeImmutable('2026-08-31T22:00:00+00:00'),
        new DateTimeImmutable('2026-09-30T22:00:00+00:00'),
    );
}

function augustInMadrid(): ReportingWindow
{
    return new ReportingWindow(
        LocalDate::fromString('2026-08-01'),
        LocalDate::fromString('2026-08-31'),
        new DateTimeImmutable('2026-07-31T22:00:00+00:00'),
        new DateTimeImmutable('2026-08-31T22:00:00+00:00'),
    );
}

beforeEach(function () {
    $this->now = new DateTimeImmutable('2026-09-29T10:00:00+00:00');
    $this->store = new ArrayStore(serializesValues: true, serializableClasses: false);
    $this->reader = new FakeStatisticsReader;

    $this->cached = new CachedStatisticsReader($this->reader, new Repository($this->store), CACHED_STATISTICS_TTL_SECONDS);
});

describe('the cache key', function () {
    it('is namespaced by the business uuid and the reading, ending in a sha256 of the window', function () {
        $this->cached->netCollectedCents(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid());

        expect(array_keys($this->store->all()))->toHaveCount(1)
            ->and(array_keys($this->store->all())[0])
            ->toMatch('/^statistics:'.CACHED_STATISTICS_BUSINESS_ID.':netCollectedCents:[0-9a-f]{64}$/');
    });

    it('expires after the time to live it was given', function () {
        $cache = Mockery::mock(Cache::class);
        $cache->shouldReceive('remember')
            ->once()
            ->with(Mockery::type('string'), CACHED_STATISTICS_TTL_SECONDS, Mockery::type(Closure::class))
            ->andReturn(0);

        (new CachedStatisticsReader($this->reader, $cache, CACHED_STATISTICS_TTL_SECONDS))
            ->netCollectedCents(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid());

        $cache->shouldHaveReceived('remember')->once();
    });
});

describe('isolation between readings', function () {
    it('answers a repeated reading from the cache', function () {
        $this->cached->netCollectedCents(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid());
        $this->cached->netCollectedCents(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid());

        expect($this->reader->callsTo('netCollectedCents'))->toHaveCount(1);
    });

    it('never answers one business with another business figures', function () {
        $this->reader->collectsBetween('2026-09-01', '2026-09-30', 125_00);
        $this->cached->netCollectedCents(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid());

        $this->reader->collectsBetween('2026-09-01', '2026-09-30', 990_00);

        expect($this->cached->netCollectedCents(CACHED_STATISTICS_OTHER_BUSINESS_ID, septemberInMadrid()))->toBe(990_00)
            ->and(array_column($this->reader->callsTo('netCollectedCents'), 'businessId'))->toBe([
                CACHED_STATISTICS_BUSINESS_ID,
                CACHED_STATISTICS_OTHER_BUSINESS_ID,
            ]);
    });

    it('reads a different window afresh', function () {
        $this->cached->netCollectedCents(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid());
        $this->cached->netCollectedCents(CACHED_STATISTICS_BUSINESS_ID, augustInMadrid());

        expect($this->reader->callsTo('netCollectedCents'))->toHaveCount(2);
    });

    it('reads the per day split afresh for a different zone', function () {
        $this->cached->netCollectedCentsPerLocalDay(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), new DateTimeZone('Europe/Madrid'));
        $this->cached->netCollectedCentsPerLocalDay(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), new DateTimeZone('America/Monterrey'));

        expect(array_map(
            static fn (array $call): string => $call['zone']->getName(),
            $this->reader->callsTo('netCollectedCentsPerLocalDay'),
        ))->toBe(['Europe/Madrid', 'America/Monterrey']);
    });

    it('answers the per day split from the cache for the same zone', function () {
        $this->cached->netCollectedCentsPerLocalDay(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), new DateTimeZone('Europe/Madrid'));
        $this->cached->netCollectedCentsPerLocalDay(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), new DateTimeZone('Europe/Madrid'));

        expect($this->reader->callsTo('netCollectedCentsPerLocalDay'))->toHaveCount(1);
    });

    it('keeps each reading under its own key for the same business and window', function () {
        $window = septemberInMadrid();

        $this->cached->netCollectedCents(CACHED_STATISTICS_BUSINESS_ID, $window);
        $this->cached->netCollectedByPaymentMethod(CACHED_STATISTICS_BUSINESS_ID, $window);
        $this->cached->staffPerformance(CACHED_STATISTICS_BUSINESS_ID, $window, $this->now);
        $this->cached->appointmentTally(CACHED_STATISTICS_BUSINESS_ID, $window, $this->now);
        $this->cached->customerTally(CACHED_STATISTICS_BUSINESS_ID, $window, $this->now);

        expect($this->reader->calls)->toHaveCount(5)
            ->and($this->store->all())->toHaveCount(5);
    });
});

describe('the round trip through a cache that unserializes no classes', function () {
    it('returns the same figures on a hit as on the miss that stored them', function (Closure $arrange, Closure $read) {
        $arrange($this->reader);

        $miss = $read($this->cached, $this->now);
        $hit = $read($this->cached, $this->now);

        expect($hit)->toEqual($miss)
            ->and($this->reader->calls)->toHaveCount(1);
    })->with([
        'net collected' => [
            static fn (FakeStatisticsReader $reader) => $reader->collectsBetween('2026-09-01', '2026-09-30', 4_250_00),
            static fn (StatisticsReader $reader) => $reader->netCollectedCents(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid()),
        ],
        'net collected per local day' => [
            static fn (FakeStatisticsReader $reader) => $reader->collectsPerDay(['2026-09-01' => 100_00, '2026-09-02' => 0]),
            static fn (StatisticsReader $reader) => $reader->netCollectedCentsPerLocalDay(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), new DateTimeZone('Europe/Madrid')),
        ],
        'net collected by payment method' => [
            static fn (FakeStatisticsReader $reader) => $reader->collectsThrough(
                new PaymentMethodCollection('cash', 300_00),
                new PaymentMethodCollection('bank_transfer', 150_00),
            ),
            static fn (StatisticsReader $reader) => $reader->netCollectedByPaymentMethod(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid()),
        ],
        'staff performance' => [
            static fn (FakeStatisticsReader $reader) => $reader->performs(
                new StaffPerformance('01930000-0000-7000-8000-0000000000d1', 'Begoña Ruiz', 'begona@example.com', 300_00, 7),
            ),
            static fn (StatisticsReader $reader, DateTimeImmutable $now) => $reader->staffPerformance(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $now),
        ],
        'appointment tally' => [
            static fn (FakeStatisticsReader $reader) => $reader->tallies(new AppointmentTally(
                total: 12,
                attended: 7,
                cancelled: 2,
                upcoming: 3,
                bookedBySource: [BookingSource::Admin->value => 4, BookingSource::Public->value => 8],
            )),
            static fn (StatisticsReader $reader, DateTimeImmutable $now) => $reader->appointmentTally(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $now),
        ],
        'customer tally' => [
            static fn (FakeStatisticsReader $reader) => $reader->counts(new CustomerTally(attended: 9, new: 4)),
            static fn (StatisticsReader $reader, DateTimeImmutable $now) => $reader->customerTally(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $now),
        ],
    ]);

    it('rebuilds the appointment tally with the bookings made through each source', function () {
        $this->reader->tallies(new AppointmentTally(
            total: 12,
            attended: 7,
            cancelled: 2,
            upcoming: 3,
            bookedBySource: [BookingSource::Admin->value => 4, BookingSource::Public->value => 8],
        ));
        $this->cached->appointmentTally(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $this->now);

        $tally = $this->cached->appointmentTally(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $this->now);

        expect($tally->total)->toBe(12)
            ->and($tally->upcoming)->toBe(3)
            ->and($tally->bookedThrough(BookingSource::Admin))->toBe(4)
            ->and($tally->bookedThrough(BookingSource::Public))->toBe(8);
    });

    it('rebuilds a tally with no bookings through a source as zero for that source', function () {
        $this->reader->tallies(new AppointmentTally(total: 1, attended: 1, cancelled: 0, upcoming: 0, bookedBySource: [BookingSource::Public->value => 1]));
        $this->cached->appointmentTally(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $this->now);

        expect($this->cached->appointmentTally(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $this->now)->bookedThrough(BookingSource::Admin))
            ->toBe(0);
    });

    it('stores plain arrays and scalars, never objects', function (Closure $read) {
        $this->reader
            ->collectsThrough(new PaymentMethodCollection('cash', 300_00))
            ->performs(new StaffPerformance('01930000-0000-7000-8000-0000000000d1', 'Begoña Ruiz', 'begona@example.com', 300_00, 7))
            ->tallies(new AppointmentTally(total: 1, attended: 1, cancelled: 0, upcoming: 0, bookedBySource: [BookingSource::Admin->value => 1]))
            ->counts(new CustomerTally(attended: 1, new: 1));

        $read($this->cached, $this->now);

        $stored = array_column($this->store->all(unserialize: false), 'value');

        expect($stored)->toHaveCount(1)
            ->and($stored[0])->toStartWith('a:')
            ->and($stored[0])->not->toContain('O:');
    })->with([
        'payment methods' => static fn (StatisticsReader $reader) => $reader->netCollectedByPaymentMethod(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid()),
        'staff performance' => static fn (StatisticsReader $reader, DateTimeImmutable $now) => $reader->staffPerformance(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $now),
        'appointment tally' => static fn (StatisticsReader $reader, DateTimeImmutable $now) => $reader->appointmentTally(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $now),
        'customer tally' => static fn (StatisticsReader $reader, DateTimeImmutable $now) => $reader->customerTally(CACHED_STATISTICS_BUSINESS_ID, septemberInMadrid(), $now),
    ]);
});
