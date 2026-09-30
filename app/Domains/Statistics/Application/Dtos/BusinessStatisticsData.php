<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Application\Dtos;

final readonly class BusinessStatisticsData
{
    /**
     * @param  list<DailyCollectionData>  $lastSevenDays
     * @param  list<PaymentMethodShareData>  $byPaymentMethod
     * @param  list<StaffShareData>  $byStaff
     */
    public function __construct(
        public string $currencyCode,
        public string $timezone,
        public PeriodCollectionData $period,
        public PeriodCollectionData $previousPeriod,
        public ?float $changePercent,
        public DailyCollectionData $today,
        public array $lastSevenDays,
        public array $byPaymentMethod,
        public array $byStaff,
        public AppointmentSummaryData $appointments,
        public CustomerSummaryData $customers,
    ) {}
}
