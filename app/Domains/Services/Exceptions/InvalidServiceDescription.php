<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidServiceDescription extends DomainException implements DomainFailure
{
    public static function tooLong(int $maximumLength): self
    {
        return new self("A service description takes up to [{$maximumLength}] characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_service_description';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
