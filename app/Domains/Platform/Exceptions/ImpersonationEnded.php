<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ImpersonationEnded extends DomainException implements DomainFailure
{
    public static function noLongerValid(): self
    {
        return new self('The impersonation held by this session expired or lost its platform admin.');
    }

    public function errorCode(): string
    {
        return 'impersonation_ended';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Unauthenticated;
    }
}
