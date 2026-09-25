<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class TeamMemberAlreadyExists extends DomainException implements DomainFailure
{
    public static function withEmail(string $email): self
    {
        return new self("[{$email}] is already a member of this business.");
    }

    public static function forAccount(string $accountId, Throwable $previous): self
    {
        return new self("Account [{$accountId}] is already a member of this business.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'team_member_already_exists';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
