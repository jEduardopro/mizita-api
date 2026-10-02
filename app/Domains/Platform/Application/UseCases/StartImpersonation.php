<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\UseCases;

use App\Domains\Platform\Application\Dtos\ImpersonationData;
use App\Domains\Platform\Application\Dtos\StartImpersonationInput;
use App\Domains\Platform\Contracts\BusinessOwnerAccounts;
use App\Domains\Platform\Contracts\ImpersonationAuditTrail;
use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Domains\Platform\Exceptions\BusinessOwnerDeactivated;
use App\Domains\Platform\ValueObjects\Impersonation;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;

final class StartImpersonation
{
    public function __construct(
        private readonly BusinessOwnerAccounts $owners,
        private readonly ImpersonationSession $session,
        private readonly ImpersonationAuditTrail $auditTrail,
        private readonly TransactionManager $transactions,
        private readonly IdGenerator $ids,
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
                id: $this->ids->next(),
                adminId: $input->adminId,
                owner: $this->owners->ownerOf($input->businessId),
                now: $this->clock->now(),
            );

            $this->startRecorded($impersonation, $input->ipAddress);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(ImpersonationData::of($impersonation));
    }

    /**
     * @throws BusinessOwnerDeactivated
     */
    private function startRecorded(Impersonation $impersonation, ?string $ipAddress): void
    {
        $this->transactions->run(function () use ($impersonation, $ipAddress): void {
            $this->auditTrail->recordStarted($impersonation, $ipAddress);

            $this->session->start($impersonation);
        });
    }
}
