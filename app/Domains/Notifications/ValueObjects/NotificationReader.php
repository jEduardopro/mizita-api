<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

use App\Domains\Notifications\Exceptions\TeamNotificationsRequireOwner;

final readonly class NotificationReader
{
    private function __construct(
        public string $staffMemberId,
        private bool $seesWholeTeam,
    ) {}

    public static function owner(string $staffMemberId): self
    {
        return new self($staffMemberId, true);
    }

    public static function member(string $staffMemberId): self
    {
        return new self($staffMemberId, false);
    }

    /**
     * @throws TeamNotificationsRequireOwner
     */
    public function audienceFor(NotificationScope $scope): NotificationAudience
    {
        if ($scope === NotificationScope::Mine) {
            return NotificationAudience::addressedTo($this->staffMemberId);
        }

        if (! $this->seesWholeTeam) {
            throw TeamNotificationsRequireOwner::forStaffMember($this->staffMemberId);
        }

        return NotificationAudience::wholeTeam();
    }

    public function canView(string $recipientStaffMemberId): bool
    {
        return $this->seesWholeTeam || $this->isRecipient($recipientStaffMemberId);
    }

    public function isRecipient(string $recipientStaffMemberId): bool
    {
        return $this->staffMemberId === $recipientStaffMemberId;
    }
}
