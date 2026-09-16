<?php

declare(strict_types=1);

namespace App\Domains\Links\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class DuplicateLinkPlatform extends DomainException implements DomainFailure
{
    public static function for(string $platform): self
    {
        return new self("Platform [{$platform}] appears more than once for the same owner.");
    }

    public function errorCode(): string
    {
        return 'duplicate_link_platform';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
