<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\EnforceActiveServiceLimitInput;
use App\Domains\Services\Contracts\ServiceAllowance;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Exceptions\ServiceAlreadyInactive;
use App\Domains\Services\Exceptions\ServiceNameAlreadyTaken;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Exceptions\ServiceSlugAlreadyTaken;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class EnforceActiveServiceLimit
{
    private const NOTHING_DEACTIVATED = 0;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceAllowance $allowance,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<int>
     */
    public function handle(EnforceActiveServiceLimitInput $input): UseCaseResponse
    {
        $limit = $this->allowance->activeServiceLimitFor($input->businessId);

        if ($limit === null) {
            return UseCaseResponse::success(self::NOTHING_DEACTIVATED);
        }

        try {
            $deactivated = $this->transactions->run(
                fn (): int => $this->deactivateBeyond($input->businessId, $limit),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($deactivated);
    }

    /**
     * @throws ServiceAlreadyInactive
     * @throws ServiceNameAlreadyTaken
     * @throws ServiceNotFound
     * @throws ServiceSlugAlreadyTaken
     */
    private function deactivateBeyond(string $businessId, int $limit): int
    {
        $this->services->lockActivationsOf($businessId);

        $surplus = array_slice($this->services->activeIdsOldestFirst($businessId), $limit);

        foreach ($surplus as $serviceId) {
            $service = $this->services->findForBusiness($businessId, $serviceId);
            $service->deactivate();
            $this->services->save($service);
        }

        return count($surplus);
    }
}
