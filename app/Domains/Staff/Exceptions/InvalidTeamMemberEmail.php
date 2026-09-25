<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class InvalidTeamMemberEmail extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A team member needs an email address.');
    }

    public static function tooLong(int $maximumLength): self
    {
        return new self("A team member email takes up to [{$maximumLength}] characters.");
    }

    public static function malformed(string $email): self
    {
        return new self("[{$email}] is not an email address.");
    }

    public static function rejectedByAccount(Throwable $previous): self
    {
        return new self('The account refused that email address.', 0, $previous);
    }

    public function errorCode(): string
    {
        return 'invalid_team_member_email';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
