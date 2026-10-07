<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects\Payloads;

use App\Domains\Notifications\ValueObjects\NotificationSubject;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\NotifiedStaffMember;

final readonly class StaffScheduleChangedPayload implements NotificationPayload
{
    private const STAFF_MEMBER = 'staff_member';

    public function __construct(
        public NotifiedStaffMember $staffMember,
    ) {}

    /**
     * @param  array<array-key, mixed>  $snapshot
     */
    public static function fromArray(array $snapshot): self
    {
        $staffMember = SnapshotReader::of($snapshot)->section(self::STAFF_MEMBER);

        return new self(new NotifiedStaffMember(
            staffMemberId: $staffMember->text('id'),
            name: $staffMember->text('name'),
        ));
    }

    public function type(): NotificationType
    {
        return NotificationType::StaffScheduleChanged;
    }

    public function subject(): NotificationSubject
    {
        return new NotificationSubject(NotificationSubjectType::StaffMember, $this->staffMember->staffMemberId);
    }

    public function idempotencyKey(string $eventId): string
    {
        return $eventId;
    }

    public function collapseKey(string $eventId): string
    {
        return $this->type()->keyFor($this->staffMember->staffMemberId);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function toArray(): array
    {
        return [
            self::STAFF_MEMBER => [
                'id' => $this->staffMember->staffMemberId,
                'name' => $this->staffMember->name,
            ],
        ];
    }
}
