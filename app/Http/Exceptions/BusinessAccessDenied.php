<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class BusinessAccessDenied extends RuntimeException implements DomainFailure
{
    private const NO_BUSINESS = 'no_business';

    private const NOT_ACCESSIBLE = 'business_not_accessible';

    private function __construct(
        string $message,
        private readonly string $errorCode,
    ) {
        parent::__construct($message);
    }

    public static function accountHasNoBusiness(): self
    {
        return new self('The caller belongs to no business.', self::NO_BUSINESS);
    }

    public static function businessNotAccessible(string $businessId): self
    {
        return new self("The caller is not a member of business [{$businessId}].", self::NOT_ACCESSIBLE);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
