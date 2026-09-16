<?php

declare(strict_types=1);

namespace App\Domains\Links\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class LinkPlatformMismatch extends DomainException implements DomainFailure
{
    public static function between(string $platform, string $host): self
    {
        return new self("Host [{$host}] does not belong to the [{$platform}] platform.");
    }

    public function errorCode(): string
    {
        return 'link_platform_mismatch';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
