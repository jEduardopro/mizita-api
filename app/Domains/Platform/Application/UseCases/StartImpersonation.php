<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\UseCases;

use App\Domains\Platform\Application\Dtos\ImpersonationData;
use App\Domains\Platform\Application\Dtos\StartImpersonationInput;
use App\Domains\Platform\Contracts\BusinessOwnerAccounts;
use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Domains\Platform\ValueObjects\Impersonation;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;

final class StartImpersonation
{
    public function __construct(
        private readonly BusinessOwnerAccounts $owners,
        private readonly ImpersonationSession $session,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<ImpersonationData>
     */
    public function handle(StartImpersonationInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $impersonation = Impersonation::begin(
                adminId: $input->adminId,
                owner: $this->owners->ownerOf($input->businessId),
                now: $this->clock->now(),
            );

            $this->session->start($impersonation);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(ImpersonationData::of($impersonation));
    }
}
