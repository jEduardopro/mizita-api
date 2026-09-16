<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class UnsupportedBusinessLogo extends DomainException implements DomainFailure
{
    public static function missing(): self
    {
        return new self('No logo was offered.');
    }

    public static function ofType(string $mimeType): self
    {
        return new self("[{$mimeType}] is not a supported business logo type.");
    }

    public function errorCode(): string
    {
        return 'unsupported_business_logo';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
