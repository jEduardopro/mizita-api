<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Http\Exceptions\BusinessAccessDenied;
use App\Http\Exceptions\TeamAccessPaused;
use App\Shared\Contracts\BusinessMembership;
use App\Shared\Contracts\BusinessSelection;
use App\Shared\Contracts\CurrentBusinessResolver;
use App\Shared\Contracts\PausedBusinessAccess;

final readonly class MembershipCurrentBusinessResolver implements CurrentBusinessResolver
{
    public function __construct(
        private BusinessMembership $memberships,
        private PausedBusinessAccess $pausedAccess,
        private BusinessSelection $selection,
    ) {}

    /**
     * @throws BusinessAccessDenied
     * @throws TeamAccessPaused
     */
    public function resolveFor(string $accountId, ?string $requestedBusinessId): string
    {
        $available = $this->memberships->businessIdsFor($accountId);

        if ($available === []) {
            $this->guardAgainstEveryMembershipPaused($accountId);

            throw BusinessAccessDenied::accountHasNoBusiness();
        }

        if ($requestedBusinessId !== null) {
            return $this->accessibleRequested($accountId, $requestedBusinessId, $available);
        }

        return $this->rememberedAmong($accountId, $available) ?? $available[0];
    }

    /**
     * @param  list<string>  $available
     *
     * @throws BusinessAccessDenied
     * @throws TeamAccessPaused
     */
    private function accessibleRequested(string $accountId, string $requestedBusinessId, array $available): string
    {
        if (! in_array($requestedBusinessId, $available, strict: true)) {
            $this->guardAgainstPausedBusiness($accountId, $requestedBusinessId);

            throw BusinessAccessDenied::businessNotAccessible($requestedBusinessId);
        }

        return $requestedBusinessId;
    }

    /**
     * @param  list<string>  $available
     */
    private function rememberedAmong(string $accountId, array $available): ?string
    {
        $remembered = $this->selection->selectedBusinessIdFor($accountId);

        if ($remembered === null) {
            return null;
        }

        if (in_array($remembered, $available, strict: true)) {
            return $remembered;
        }

        $this->selection->forgetFor($accountId);

        return null;
    }

    private function guardAgainstEveryMembershipPaused(string $accountId): void
    {
        if ($this->pausedAccess->pausedBusinessIdsFor($accountId) !== []) {
            throw TeamAccessPaused::forEveryMembership();
        }
    }

    private function guardAgainstPausedBusiness(string $accountId, string $businessId): void
    {
        if (in_array($businessId, $this->pausedAccess->pausedBusinessIdsFor($accountId), strict: true)) {
            throw TeamAccessPaused::forBusiness($businessId);
        }
    }
}
