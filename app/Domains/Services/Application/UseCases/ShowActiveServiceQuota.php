<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\ActiveServiceQuotaData;
use App\Domains\Services\Contracts\ServiceAllowance;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;

final class ShowActiveServiceQuota
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceAllowance $allowance,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<ActiveServiceQuotaData>
     */
    public function handle(): UseCaseResponse
    {
        $businessId = $this->business->currentBusinessId();

        return UseCaseResponse::success(new ActiveServiceQuotaData(
            activeCount: $this->services->countActive($businessId),
            activeLimit: $this->allowance->activeServiceLimitFor($businessId),
        ));
    }
}
