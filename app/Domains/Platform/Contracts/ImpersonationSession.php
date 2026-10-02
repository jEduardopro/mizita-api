<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

use App\Domains\Platform\Exceptions\BusinessOwnerDeactivated;
use App\Domains\Platform\ValueObjects\Impersonation;

interface ImpersonationSession
{
    /**
     * @throws BusinessOwnerDeactivated
     */
    public function start(Impersonation $impersonation): void;

    public function end(): void;
}
