<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Application\UseCases;

use App\Domains\Statistics\Application\Dtos\AppointmentSummaryData;
use App\Domains\Statistics\Application\Dtos\BusinessStatisticsData;
use App\Domains\Statistics\Application\Dtos\CustomerSummaryData;
use App\Domains\Statistics\Application\Dtos\DailyCollectionData;
use App\Domains\Statistics\Application\Dtos\PaymentMethodShareData;
use App\Domains\Statistics\Application\Dtos\PeriodCollectionData;
use App\Domains\Statistics\Application\Dtos\ShowStatisticsInput;
use App\Domains\Statistics\Application\Dtos\StaffShareData;
use App\Domains\Statistics\Contracts\BusinessCurrency;
use App\Domains\Statistics\Contracts\BusinessTimezone;
use App\Domains\Statistics\Contracts\StatisticsReader;
use App\Domains\Statistics\Services\PercentageCalculator;
use App\Domains\Statistics\Services\StatisticsCalendar;
use App\Domains\Statistics\ValueObjects\LocalDate;
use App\Domains\Statistics\ValueObjects\ReportingWindow;
use App\Domains\Statistics\ValueObjects\StatisticsWindows;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use DateTimeImmutable;
use DateTimeZone;

final class ShowBusinessStatistics
{
    public function __construct(
        private readonly StatisticsReader $statistics,
        private readonly BusinessTimezone $timezones,
        private readonly BusinessCurrency $currencies,
        private readonly BusinessContext $business,
        private readonly Clock $clock,
        private readonly StatisticsCalendar $calendar,
        private readonly PercentageCalculator $percentages,
    ) {}

    /**
     * @return UseCaseResponse<BusinessStatisticsData>
     */
    public function handle(ShowStatisticsInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $zone = $this->timezones->timezoneOf($businessId);
            $now = $this->clock->now();
            $windows = $this->calendar->windowsFor($now, $zone, $input->toPeriod());

            return UseCaseResponse::success($this->report($businessId, $zone, $now, $windows));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    private function report(
        string $businessId,
        DateTimeZone $zone,
        DateTimeImmutable $now,
        StatisticsWindows $windows,
    ): BusinessStatisticsData {
        $collected = $this->statistics->netCollectedCents($businessId, $windows->current);
        $previouslyCollected = $this->statistics->netCollectedCents($businessId, $windows->previous);
        $collectedToday = $this->statistics->netCollectedCents($businessId, $windows->today);

        return new BusinessStatisticsData(
            currencyCode: $this->currencies->currencyOf($businessId)->value,
            timezone: $zone->getName(),
            period: PeriodCollectionData::of($windows->current, $collected),
            previousPeriod: PeriodCollectionData::of($windows->previous, $previouslyCollected),
            changePercent: $this->percentages->changeBetween($previouslyCollected, $collected),
            today: DailyCollectionData::of($windows->today->from, $collectedToday),
            lastSevenDays: $this->lastSevenDays($businessId, $zone, $windows->lastSevenDays),
            byPaymentMethod: $this->byPaymentMethod($businessId, $windows->current, $collected),
            byStaff: $this->byStaff($businessId, $windows->current, $now, $collected),
            appointments: AppointmentSummaryData::fromTally(
                $this->statistics->appointmentTally($businessId, $windows->current, $now),
            ),
            customers: CustomerSummaryData::fromTally(
                $this->statistics->customerTally($businessId, $windows->current, $now),
            ),
        );
    }

    /**
     * @return list<DailyCollectionData>
     */
    private function lastSevenDays(string $businessId, DateTimeZone $zone, ReportingWindow $window): array
    {
        $collectedPerDay = $this->statistics->netCollectedCentsPerLocalDay($businessId, $window, $zone);

        return array_map(
            static fn (LocalDate $date): DailyCollectionData => DailyCollectionData::of(
                $date,
                $collectedPerDay[$date->toString()] ?? 0,
            ),
            $window->dates(),
        );
    }

    /**
     * @return list<PaymentMethodShareData>
     */
    private function byPaymentMethod(string $businessId, ReportingWindow $window, int $totalCents): array
    {
        $methods = [];

        foreach ($this->statistics->netCollectedByPaymentMethod($businessId, $window) as $method) {
            $methods[] = new PaymentMethodShareData(
                code: $method->code,
                collectedCents: $method->collectedCents,
                sharePercent: $this->percentages->shareOf($method->collectedCents, $totalCents),
            );
        }

        return $methods;
    }

    /**
     * @return list<StaffShareData>
     */
    private function byStaff(string $businessId, ReportingWindow $window, DateTimeImmutable $now, int $totalCents): array
    {
        $staff = [];

        foreach ($this->statistics->staffPerformance($businessId, $window, $now) as $performance) {
            $staff[] = new StaffShareData(
                id: $performance->staffMemberId,
                name: $performance->name,
                email: $performance->email,
                collectedCents: $performance->collectedCents,
                sharePercent: $this->percentages->shareOf($performance->collectedCents, $totalCents),
                attendedAppointments: $performance->attendedAppointments,
            );
        }

        return $staff;
    }
}
