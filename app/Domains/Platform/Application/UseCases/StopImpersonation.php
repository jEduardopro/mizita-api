<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\UseCases;

use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Shared\Application\UseCaseResponse;

final class StopImpersonation
{
    public function __construct(
        private readonly ImpersonationSession $session,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(): UseCaseResponse
    {
        $this->session->end();

        return UseCaseResponse::success();
    }
}
