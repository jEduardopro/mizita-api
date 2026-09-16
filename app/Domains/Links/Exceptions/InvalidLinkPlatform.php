<?php

declare(strict_types=1);

namespace App\Domains\Links\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidLinkPlatform extends DomainException implements DomainFailure
{
    public static function withValue(string $value): self
    {
        return new self("[{$value}] is not a platform a link may point at.");
    }

    public function errorCode(): string
    {
        return 'invalid_link_platform';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
