<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PlatformSessionExpired extends DomainException implements DomainFailure
{
    public static function signedOut(): self
    {
        return new self('No platform admin is signed in to this session any longer.');
    }

    public function errorCode(): string
    {
        return 'platform_session_expired';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Unauthenticated;
    }
}
