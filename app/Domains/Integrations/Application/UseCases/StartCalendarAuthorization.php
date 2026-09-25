<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\UseCases;

use App\Domains\Integrations\Application\Dtos\AuthorizationUrlData;
use App\Domains\Integrations\Application\Dtos\StartCalendarAuthorizationInput;
use App\Domains\Integrations\Contracts\CalendarAuthorizationStates;
use App\Domains\Integrations\Contracts\CalendarAuthorizer;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarOwners;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\PendingAuthorization;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class StartCalendarAuthorization
{
    private const PROVIDER = CalendarProvider::Google;

    public function __construct(
        private readonly CalendarOwners $owners,
        private readonly CalendarConnectionRepository $connections,
        private readonly CalendarAuthorizationStates $states,
        private readonly CalendarAuthorizer $authorizer,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<AuthorizationUrlData>
     */
    public function handle(StartCalendarAuthorizationInput $input): UseCaseResponse
    {
        try {
            $businessId = $this->business->currentBusinessId();
            $staffMemberId = $this->owners->staffMemberIdOf($businessId, $input->accountId);

            $this->connections
                ->findForStaffMember($businessId, $staffMemberId, self::PROVIDER)
                ?->assertAwaitingReconnect();

            $state = $this->states->issue(new PendingAuthorization($input->accountId, $businessId, $staffMemberId));

            return UseCaseResponse::success(new AuthorizationUrlData($this->authorizer->authorizationUrl($state)));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
