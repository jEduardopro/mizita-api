<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use LogicException;
use Throwable;

/**
 * @template TData
 */
final readonly class UseCaseResponse
{
    /**
     * @param  TData|null  $data
     * @param  list<Warning>  $warnings
     */
    private function __construct(
        private mixed $data,
        private ?UseCaseError $error,
        private array $warnings,
    ) {}

    /**
     * @template TValue
     *
     * @param  TValue  $data
     * @param  list<Warning>  $warnings
     * @return self<TValue>
     */
    public static function success(mixed $data = null, array $warnings = []): self
    {
        return new self($data, null, $warnings);
    }

    /**
     * @return self<never>
     */
    public static function failure(DomainFailure&Throwable $failure): self
    {
        return new self(null, UseCaseError::from($failure), []);
    }

    /**
     * @return self<never>
     */
    public static function invalid(string $code): self
    {
        return self::failureOf($code, DomainFailureKind::Invalid);
    }

    /**
     * @return self<never>
     */
    public static function conflict(string $code): self
    {
        return self::failureOf($code, DomainFailureKind::Conflict);
    }

    /**
     * @return self<never>
     */
    public static function notFound(string $code): self
    {
        return self::failureOf($code, DomainFailureKind::NotFound);
    }

    /**
     * @return self<never>
     */
    public static function unauthenticated(string $code): self
    {
        return self::failureOf($code, DomainFailureKind::Unauthenticated);
    }

    /**
     * @return self<never>
     */
    public static function forbidden(string $code): self
    {
        return self::failureOf($code, DomainFailureKind::Forbidden);
    }

    /**
     * @return self<TData>
     */
    public function addWarning(string $code): self
    {
        return new self(
            $this->data,
            $this->error,
            [...$this->warnings, new Warning($code)],
        );
    }

    /**
     * @return list<Warning>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function succeeded(): bool
    {
        return $this->error === null;
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    public function error(): UseCaseError
    {
        if ($this->error === null) {
            throw new LogicException('A succeeded use case response carries no error.');
        }

        return $this->error;
    }

    /**
     * @return TData
     */
    public function value(): mixed
    {
        if ($this->error !== null) {
            throw $this->error->toThrowable();
        }

        return $this->data;
    }

    /**
     * @return self<never>
     */
    private static function failureOf(string $code, DomainFailureKind $kind): self
    {
        return new self(null, UseCaseError::of($code, $kind), []);
    }
}
