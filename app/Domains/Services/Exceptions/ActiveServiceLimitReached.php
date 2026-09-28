<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ActiveServiceLimitReached extends DomainException implements DomainFailure
{
    public static function of(int $limit): self
    {
        return new self("The business already has the {$limit} active services its plan allows.");
    }

    public function errorCode(): string
    {
        return 'active_service_limit_reached';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
