<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class PermissionDenied extends RuntimeException implements DomainFailure
{
    private const MISSING_PERMISSION = 'missing_permission';

    public static function forCurrentBusiness(): self
    {
        return new self('The caller is not allowed to perform this action in this business.');
    }

    public function errorCode(): string
    {
        return self::MISSING_PERMISSION;
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
