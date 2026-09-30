<?php

declare(strict_types=1);

namespace Tests\Support\Statistics;

use App\Domains\Statistics\Contracts\StatisticsReader;
use App\Domains\Statistics\ValueObjects\AppointmentTally;
use App\Domains\Statistics\ValueObjects\CustomerTally;
use App\Domains\Statistics\ValueObjects\PaymentMethodCollection;
use App\Domains\Statistics\ValueObjects\ReportingWindow;
use App\Domains\Statistics\ValueObjects\StaffPerformance;
use DateTimeImmutable;
use DateTimeZone;

final class FakeStatisticsReader implements StatisticsReader
{
    /**
     * @var array<string, int>
     */
    private array $collectedByPeriod = [];

    /**
     * @var array<string, int>
     */
    private array $collectedPerDay = [];

    /**
     * @var list<PaymentMethodCollection>
     */
    private array $methods = [];

    /**
     * @var list<StaffPerformance>
     */
    private array $staff = [];

    private AppointmentTally $appointments;

    private CustomerTally $customers;

    /**
     * @var list<array{method: string, businessId: string, window: ReportingWindow, zone: ?DateTimeZone, now: ?DateTimeImmutable}>
     */
    public array $calls = [];

    public function __construct()
    {
        $this->appointments = new AppointmentTally(total: 0, attended: 0, cancelled: 0, upcoming: 0, bookedBySource: []);
        $this->customers = new CustomerTally(attended: 0, new: 0);
    }

    public function collectsBetween(string $from, string $to, int $cents): self
    {
        $this->collectedByPeriod[$from.'/'.$to] = $cents;

        return $this;
    }

    /**
     * @param  array<string, int>  $collectedPerDay
     */
    public function collectsPerDay(array $collectedPerDay): self
    {
        $this->collectedPerDay = $collectedPerDay;

        return $this;
    }

    public function collectsThrough(PaymentMethodCollection ...$methods): self
    {
        $this->methods = array_values($methods);

        return $this;
    }

    public function performs(StaffPerformance ...$staff): self
    {
        $this->staff = array_values($staff);

        return $this;
    }

    public function tallies(AppointmentTally $appointments): self
    {
        $this->appointments = $appointments;

        return $this;
    }

    public function counts(CustomerTally $customers): self
    {
        $this->customers = $customers;

        return $this;
    }

    public function netCollectedCents(string $businessId, ReportingWindow $window): int
    {
        $this->record(__FUNCTION__, $businessId, $window);

        return $this->collectedByPeriod[$window->from->toString().'/'.$window->to->toString()] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function netCollectedCentsPerLocalDay(string $businessId, ReportingWindow $window, DateTimeZone $zone): array
    {
        $this->record(__FUNCTION__, $businessId, $window, zone: $zone);

        return $this->collectedPerDay;
    }

    /**
     * @return list<PaymentMethodCollection>
     */
    public function netCollectedByPaymentMethod(string $businessId, ReportingWindow $window): array
    {
        $this->record(__FUNCTION__, $businessId, $window);

        return $this->methods;
    }

    /**
     * @return list<StaffPerformance>
     */
    public function staffPerformance(string $businessId, ReportingWindow $window, DateTimeImmutable $now): array
    {
        $this->record(__FUNCTION__, $businessId, $window, now: $now);

        return $this->staff;
    }

    public function appointmentTally(string $businessId, ReportingWindow $window, DateTimeImmutable $now): AppointmentTally
    {
        $this->record(__FUNCTION__, $businessId, $window, now: $now);

        return $this->appointments;
    }

    public function customerTally(string $businessId, ReportingWindow $window, DateTimeImmutable $now): CustomerTally
    {
        $this->record(__FUNCTION__, $businessId, $window, now: $now);

        return $this->customers;
    }

    /**
     * @return list<array{method: string, businessId: string, window: ReportingWindow, zone: ?DateTimeZone, now: ?DateTimeImmutable}>
     */
    public function callsTo(string $method): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn (array $call): bool => $call['method'] === $method,
        ));
    }

    private function record(
        string $method,
        string $businessId,
        ReportingWindow $window,
        ?DateTimeZone $zone = null,
        ?DateTimeImmutable $now = null,
    ): void {
        $this->calls[] = [
            'method' => $method,
            'businessId' => $businessId,
            'window' => $window,
            'zone' => $zone,
            'now' => $now,
        ];
    }
}
