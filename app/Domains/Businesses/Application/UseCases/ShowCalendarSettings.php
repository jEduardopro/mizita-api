<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\CalendarSettingsData;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\BusinessSchedule;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ShowCalendarSettings
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly BusinessSchedule $schedule,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<CalendarSettingsData>
     */
    public function handle(): UseCaseResponse
    {
        try {
            return UseCaseResponse::success($this->describe($this->business->currentBusinessId()));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws BusinessNotFound
     */
    private function describe(string $businessId): CalendarSettingsData
    {
        $business = $this->businesses->findById($businessId);

        return new CalendarSettingsData(
            timezone: $business->timezone(),
            currencyCode: $business->currency(),
            schedule: $this->schedule->forBusiness($businessId),
        );
    }
}
