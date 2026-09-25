<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\UseCases;

use App\Domains\Integrations\Application\Dtos\CalendarConnectionData;
use App\Domains\Integrations\Application\Dtos\IntegrationData;
use App\Domains\Integrations\Application\Dtos\ListIntegrationsInput;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarOwners;
use App\Domains\Integrations\ValueObjects\IntegrationKey;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ListIntegrations
{
    public function __construct(
        private readonly CalendarOwners $owners,
        private readonly CalendarConnectionRepository $connections,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<list<IntegrationData>>
     */
    public function handle(ListIntegrationsInput $input): UseCaseResponse
    {
        try {
            $businessId = $this->business->currentBusinessId();
            $staffMemberId = $this->owners->staffMemberIdOf($businessId, $input->accountId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(array_map(
            fn (IntegrationKey $key): IntegrationData => $this->describe($key, $businessId, $staffMemberId),
            IntegrationKey::cases(),
        ));
    }

    private function describe(IntegrationKey $key, string $businessId, string $staffMemberId): IntegrationData
    {
        $connection = $this->connections->findForStaffMember($businessId, $staffMemberId, $key->calendarProvider());

        return new IntegrationData(
            key: $key,
            category: $key->category(),
            connection: $connection === null ? null : CalendarConnectionData::fromEntity($connection),
        );
    }
}
