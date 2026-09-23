<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentAccountNotFound extends DomainException implements DomainFailure
{
    public static function withId(string $accountId): self
    {
        return new self("Account [{$accountId}] was not found.");
    }

    public function errorCode(): string
    {
        return 'payment_account_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
