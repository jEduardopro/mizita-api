<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBusinessContactEmail extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A business contact email cannot be empty.');
    }

    public static function tooLong(int $maximum): self
    {
        return new self("A business contact email cannot be longer than {$maximum} characters.");
    }

    public static function malformed(): self
    {
        return new self('That is not a well formed business contact email.');
    }

    public function errorCode(): string
    {
        return 'invalid_business_contact_email';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
