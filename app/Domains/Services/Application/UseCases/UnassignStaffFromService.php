<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Dtos\UnassignStaffFromServiceInput;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Contracts\StaffDirectory;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class UnassignStaffFromService
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly StaffDirectory $staff,
        private readonly ServicePresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<ServiceData>
     */
    public function handle(UnassignStaffFromServiceInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $service = $this->services->findForBusiness($businessId, $input->serviceId);

            $this->ensureWorksAtBusiness($businessId, $input->staffMemberId);

            $service->withdrawFrom($input->staffMemberId);
            $this->services->save($service);

            return UseCaseResponse::success($this->presenter->describe($service));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws UnknownStaffMember
     */
    private function ensureWorksAtBusiness(string $businessId, string $staffMemberId): void
    {
        if ($this->staff->membersOf($businessId, [$staffMemberId]) === []) {
            throw UnknownStaffMember::amongSelected();
        }
    }
}
