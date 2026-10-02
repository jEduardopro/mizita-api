<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\UseCases;

use App\Domains\Platform\Contracts\ImpersonationAuditTrail;
use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;

final class StopImpersonation
{
    public function __construct(
        private readonly ImpersonationSession $session,
        private readonly ImpersonationAuditTrail $auditTrail,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(): UseCaseResponse
    {
        $impersonation = $this->session->current();

        $this->session->end();

        if ($impersonation === null) {
            return UseCaseResponse::success();
        }

        $this->auditTrail->recordEnded($impersonation->id, $impersonation->endedAt($this->clock->now()));

        return UseCaseResponse::success();
    }
}
