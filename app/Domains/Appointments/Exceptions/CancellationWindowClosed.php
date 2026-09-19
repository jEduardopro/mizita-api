<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CancellationWindowClosed extends DomainException implements DomainFailure
{
    public static function beforeStart(): self
    {
        return new self('The window for changing this booking has already closed.');
    }

    public function errorCode(): string
    {
        return 'cancellation_window_closed';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
