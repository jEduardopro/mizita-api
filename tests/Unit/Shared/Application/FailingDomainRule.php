<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class FailingDomainRule extends RuntimeException implements DomainFailure
{
    public function __construct(
        private readonly string $errorCode = 'account_already_owns_business',
        private readonly DomainFailureKind $kind = DomainFailureKind::Conflict,
    ) {
        parent::__construct("The domain refused with [{$errorCode}].");
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function kind(): DomainFailureKind
    {
        return $this->kind;
    }
}
