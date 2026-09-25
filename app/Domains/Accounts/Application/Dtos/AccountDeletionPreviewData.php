<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\ValueObjects\DeletionBlocker;
use App\Domains\Accounts\ValueObjects\OwnedBusinessSnapshot;
use DateTimeImmutable;

final readonly class AccountDeletionPreviewData
{
    public function __construct(
        public string $email,
        public bool $hasPassword,
        public ?OwnedBusinessSnapshot $ownedBusiness,
        public int $upcomingAppointmentsCount,
        public ?DeletionBlocker $blockedBy,
        public DateTimeImmutable $gracePeriodEndsAt,
    ) {}
}
