<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class TeamAccessPaused extends RuntimeException implements DomainFailure
{
    private const TEAM_ACCESS_PAUSED = 'team_access_paused';

    public static function forEveryMembership(): self
    {
        return new self('Every business the caller belongs to has paused team access.');
    }

    public static function forBusiness(string $businessId): self
    {
        return new self("Team access to business [{$businessId}] is paused by its plan.");
    }

    public function errorCode(): string
    {
        return self::TEAM_ACCESS_PAUSED;
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
