<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidManageToken extends DomainException implements DomainFailure
{
    public static function malformed(): self
    {
        return new self('That management link is not a readable credential.');
    }

    public function errorCode(): string
    {
        return 'invalid_manage_token';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
