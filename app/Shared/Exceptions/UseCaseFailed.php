<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class UseCaseFailed extends RuntimeException implements DomainFailure
{
    private function __construct(
        private readonly string $errorCode,
        private readonly DomainFailureKind $kind,
    ) {
        parent::__construct("Use case failed with [{$errorCode}].");
    }

    public static function with(string $code, DomainFailureKind $kind): self
    {
        return new self($code, $kind);
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
