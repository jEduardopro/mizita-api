<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Contracts;

use App\Domains\Statistics\ValueObjects\AppointmentTally;
use App\Domains\Statistics\ValueObjects\CustomerTally;
use App\Domains\Statistics\ValueObjects\PaymentMethodCollection;
use App\Domains\Statistics\ValueObjects\ReportingWindow;
use App\Domains\Statistics\ValueObjects\StaffPerformance;
use DateTimeImmutable;
use DateTimeZone;

interface StatisticsReader
{
    public function netCollectedCents(string $businessId, ReportingWindow $window): int;

    /**
     * @return array<string, int>
     */
    public function netCollectedCentsPerLocalDay(string $businessId, ReportingWindow $window, DateTimeZone $zone): array;

    /**
     * @return list<PaymentMethodCollection>
     */
    public function netCollectedByPaymentMethod(string $businessId, ReportingWindow $window): array;

    /**
     * @return list<StaffPerformance>
     */
    public function staffPerformance(string $businessId, ReportingWindow $window, DateTimeImmutable $now): array;

    public function appointmentTally(string $businessId, ReportingWindow $window, DateTimeImmutable $now): AppointmentTally;

    public function customerTally(string $businessId, ReportingWindow $window, DateTimeImmutable $now): CustomerTally;
}
