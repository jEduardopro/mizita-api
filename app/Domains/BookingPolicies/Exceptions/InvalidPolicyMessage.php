<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPolicyMessage extends DomainException implements DomainFailure
{
    public static function tooLong(int $maximumLength): self
    {
        return new self("A booking policy message takes up to [{$maximumLength}] characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_policy_message';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
