<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\ValueObjects\ClosedBusinessSnapshot;
use DateTimeImmutable;

final readonly class AccountReactivationData
{
    private function __construct(
        public string $email,
        public string $name,
        public DateTimeImmutable $deletionRequestedAt,
        public DateTimeImmutable $gracePeriodEndsAt,
        public ?ClosedBusinessSnapshot $business,
    ) {}

    public static function of(Account $account, ?ClosedBusinessSnapshot $business): self
    {
        return new self(
            email: $account->email(),
            name: $account->name(),
            deletionRequestedAt: $account->deletionRequestedAt(),
            gracePeriodEndsAt: $account->gracePeriodEndsAt(),
            business: $business,
        );
    }
}
