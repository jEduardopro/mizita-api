<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Contracts\DomainFailure;
use App\Shared\Exceptions\UseCaseFailed;
use App\Shared\ValueObjects\DomainFailureKind;
use Throwable;

final readonly class UseCaseError
{
    private function __construct(
        public string $code,
        public DomainFailureKind $kind,
        private ?Throwable $cause,
    ) {}

    public static function from(DomainFailure&Throwable $failure): self
    {
        return new self($failure->errorCode(), $failure->kind(), $failure);
    }

    public static function of(string $code, DomainFailureKind $kind): self
    {
        return new self($code, $kind, null);
    }

    public function cause(): ?Throwable
    {
        return $this->cause;
    }

    public function toThrowable(): Throwable
    {
        return $this->cause ?? UseCaseFailed::with($this->code, $this->kind);
    }
}
