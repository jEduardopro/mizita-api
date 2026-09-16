<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBusinessAbout extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A business description cannot be empty.');
    }

    public static function tooLong(int $maximum): self
    {
        return new self("A business description cannot be longer than {$maximum} characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_business_about';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
