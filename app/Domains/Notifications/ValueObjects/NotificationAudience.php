<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

final readonly class NotificationAudience
{
    private function __construct(
        private ?string $recipientStaffMemberId,
    ) {}

    public static function wholeTeam(): self
    {
        return new self(null);
    }

    public static function addressedTo(string $staffMemberId): self
    {
        return new self($staffMemberId);
    }

    public function recipientFilter(): ?string
    {
        return $this->recipientStaffMemberId;
    }
}
