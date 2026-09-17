<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class UnsupportedCustomerPhoto extends DomainException implements DomainFailure
{
    public static function missing(): self
    {
        return new self('No photo was offered.');
    }

    public static function ofType(string $mimeType): self
    {
        return new self("[{$mimeType}] is not a supported customer photo type.");
    }

    public function errorCode(): string
    {
        return 'unsupported_customer_photo';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
