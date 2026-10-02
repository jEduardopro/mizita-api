<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Infrastructure\Gateways;

use App\Domains\Statistics\Contracts\StatisticsReader;
use App\Domains\Statistics\ValueObjects\AppointmentTally;
use App\Domains\Statistics\ValueObjects\BookingSource;
use App\Domains\Statistics\ValueObjects\CustomerTally;
use App\Domains\Statistics\ValueObjects\PaymentMethodCollection;
use App\Domains\Statistics\ValueObjects\ReportingWindow;
use App\Domains\Statistics\ValueObjects\StaffPerformance;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Cache\Repository as Cache;

final class CachedStatisticsReader implements StatisticsReader
{
    private const KEY_PREFIX = 'statistics:';

    public function __construct(
        private readonly StatisticsReader $reader,
        private readonly Cache $cache,
        private readonly int $ttlSeconds,
    ) {}

    public function netCollectedCents(string $businessId, ReportingWindow $window): int
    {
        return (int) $this->remember(
            self::keyFor(__FUNCTION__, $businessId, $window),
            fn (): int => $this->reader->netCollectedCents($businessId, $window),
        );
    }

    /**
     * @return array<string, int>
     */
    public function netCollectedCentsPerLocalDay(string $businessId, ReportingWindow $window, DateTimeZone $zone): array
    {
        /** @var array<string, int> $collectedPerDay */
        $collectedPerDay = $this->remember(
            self::keyFor(__FUNCTION__, $businessId, $window, $zone->getName()),
            fn (): array => $this->reader->netCollectedCentsPerLocalDay($businessId, $window, $zone),
        );

        return $collectedPerDay;
    }

    /**
     * @return list<PaymentMethodCollection>
     */
    public function netCollectedByPaymentMethod(string $businessId, ReportingWindow $window): array
    {
        /** @var list<array{code: string, collectedCents: int}> $methods */
        $methods = $this->remember(
            self::keyFor(__FUNCTION__, $businessId, $window),
            fn (): array => array_map(
                get_object_vars(...),
                $this->reader->netCollectedByPaymentMethod($businessId, $window),
            ),
        );

        return array_map(
            static fn (array $method): PaymentMethodCollection => new PaymentMethodCollection(...$method),
            $methods,
        );
    }

    /**
     * @return list<StaffPerformance>
     */
    public function staffPerformance(string $businessId, ReportingWindow $window, DateTimeImmutable $now): array
    {
        /** @var list<array{staffMemberId: string, name: string, email: string, collectedCents: int, attendedAppointments: int}> $performances */
        $performances = $this->remember(
            self::keyFor(__FUNCTION__, $businessId, $window),
            fn (): array => array_map(
                get_object_vars(...),
                $this->reader->staffPerformance($businessId, $window, $now),
            ),
        );

        return array_map(
            static fn (array $performance): StaffPerformance => new StaffPerformance(...$performance),
            $performances,
        );
    }

    public function appointmentTally(string $businessId, ReportingWindow $window, DateTimeImmutable $now): AppointmentTally
    {
        /** @var array{total: int, attended: int, cancelled: int, upcoming: int, bookedBySource: array<string, int>} $tally */
        $tally = $this->remember(
            self::keyFor(__FUNCTION__, $businessId, $window),
            fn (): array => self::appointmentTallyFields($this->reader->appointmentTally($businessId, $window, $now)),
        );

        return new AppointmentTally(...$tally);
    }

    public function customerTally(string $businessId, ReportingWindow $window, DateTimeImmutable $now): CustomerTally
    {
        /** @var array{attended: int, new: int} $tally */
        $tally = $this->remember(
            self::keyFor(__FUNCTION__, $businessId, $window),
            fn (): array => get_object_vars($this->reader->customerTally($businessId, $window, $now)),
        );

        return new CustomerTally(...$tally);
    }

    private function remember(string $key, Closure $read): mixed
    {
        return $this->cache->remember($key, $this->ttlSeconds, $read);
    }

    /**
     * @return array{total: int, attended: int, cancelled: int, upcoming: int, bookedBySource: array<string, int>}
     */
    private static function appointmentTallyFields(AppointmentTally $tally): array
    {
        $bookedBySource = [];

        foreach (BookingSource::cases() as $source) {
            $bookedBySource[$source->value] = $tally->bookedThrough($source);
        }

        return [
            'total' => $tally->total,
            'attended' => $tally->attended,
            'cancelled' => $tally->cancelled,
            'upcoming' => $tally->upcoming,
            'bookedBySource' => $bookedBySource,
        ];
    }

    private static function keyFor(string $reading, string $businessId, ReportingWindow $window, string ...$qualifiers): string
    {
        return self::KEY_PREFIX.$businessId.':'.$reading.':'.hash('sha256', implode('|', [
            $window->startsAt->getTimestamp(),
            $window->endsAt->getTimestamp(),
            ...$qualifiers,
        ]));
    }
}
