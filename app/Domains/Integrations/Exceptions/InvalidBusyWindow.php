<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBusyWindow extends DomainException implements DomainFailure
{
    public static function endsBeforeItStarts(): self
    {
        return new self('A busy window must end after it starts.');
    }

    public function errorCode(): string
    {
        return 'invalid_busy_window';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
