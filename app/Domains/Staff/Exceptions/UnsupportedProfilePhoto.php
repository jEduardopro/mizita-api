<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class UnsupportedProfilePhoto extends DomainException implements DomainFailure
{
    public static function missing(): self
    {
        return new self('No photo was offered.');
    }

    public static function ofType(string $mimeType): self
    {
        return new self("[{$mimeType}] is not a supported profile photo type.");
    }

    public function errorCode(): string
    {
        return 'unsupported_profile_photo';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
