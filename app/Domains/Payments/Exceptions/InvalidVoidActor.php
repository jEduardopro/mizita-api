<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidVoidActor extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A payment transaction can only be voided by an identified account.');
    }

    public static function malformed(string $value): self
    {
        return new self("[{$value}] is not a well formed account identifier.");
    }

    public function errorCode(): string
    {
        return 'invalid_void_actor';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
