<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidTeamSearch extends DomainException implements DomainFailure
{
    public static function tooLong(int $maximum): self
    {
        return new self("A team search may not run past {$maximum} characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_team_search';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
