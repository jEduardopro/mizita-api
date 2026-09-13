<?php

declare(strict_types=1);

namespace App\Domains\Industries\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class IndustryNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Industry [{$id}] was not found.");
    }

    public function errorCode(): string
    {
        return 'industry_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
