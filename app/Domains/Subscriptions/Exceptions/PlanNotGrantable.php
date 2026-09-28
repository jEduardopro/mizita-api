<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PlanNotGrantable extends DomainException implements DomainFailure
{
    public static function forPlan(string $plan): self
    {
        return new self("The [{$plan}] plan cannot be granted as a subscription.");
    }

    public function errorCode(): string
    {
        return 'plan_not_grantable';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
