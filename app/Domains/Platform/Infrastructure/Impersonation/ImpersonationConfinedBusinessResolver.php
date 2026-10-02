<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Impersonation;

use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Domains\Platform\Exceptions\ImpersonationConfinedToBusiness;
use App\Shared\Contracts\CurrentBusinessResolver;

final readonly class ImpersonationConfinedBusinessResolver implements CurrentBusinessResolver
{
    public function __construct(
        private CurrentBusinessResolver $memberships,
        private ImpersonationSession $impersonations,
    ) {}

    /**
     * @throws ImpersonationConfinedToBusiness
     */
    public function resolveFor(string $accountId, ?string $requestedBusinessId): string
    {
        $impersonation = $this->impersonations->current();

        if ($impersonation === null) {
            return $this->memberships->resolveFor($accountId, $requestedBusinessId);
        }

        return $this->memberships->resolveFor($accountId, $impersonation->businessToOperate($requestedBusinessId));
    }
}
