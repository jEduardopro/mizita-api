<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CalendarScopeNotGranted extends DomainException implements DomainFailure
{
    public static function forScope(string $scope): self
    {
        return new self("The calendar scope [{$scope}] was not granted.");
    }

    public function errorCode(): string
    {
        return 'calendar_scope_not_granted';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
