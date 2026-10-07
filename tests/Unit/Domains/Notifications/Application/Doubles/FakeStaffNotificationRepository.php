<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;

final class FakeStaffNotificationRepository implements StaffNotificationRepository
{
    /**
     * @var array<string, StaffNotification>
     */
    private array $notifications = [];

    /**
     * @var list<StaffNotification>
     */
    public array $added = [];

    /**
     * @var list<StaffNotification>
     */
    public array $saved = [];

    /**
     * @var list<array{businessId: string, id: string}>
     */
    public array $lookups = [];

    public function __construct(
        private readonly NotificationsJournal $journal = new NotificationsJournal,
    ) {}

    public function store(StaffNotification ...$notifications): self
    {
        foreach ($notifications as $notification) {
            $this->notifications[$notification->id] = $notification;
        }

        return $this;
    }

    public function stored(string $id): ?StaffNotification
    {
        return isset($this->notifications[$id]) ? self::copyOf($this->notifications[$id]) : null;
    }

    public function findForBusiness(string $businessId, string $id): StaffNotification
    {
        $this->journal->record('notifications.findForBusiness');
        $this->lookups[] = ['businessId' => $businessId, 'id' => $id];

        $notification = $this->notifications[$id] ?? null;

        if ($notification === null || $notification->businessId !== $businessId) {
            throw StaffNotificationNotFound::withId($id);
        }

        return self::copyOf($notification);
    }

    public function addOnce(StaffNotification $notification): void
    {
        $this->journal->record('notifications.addOnce');
        $this->added[] = $notification;
        $this->notifications[$notification->id] = self::copyOf($notification);
    }

    public function save(StaffNotification $notification): void
    {
        $this->journal->record('notifications.save');
        $this->saved[] = $notification;
        $this->notifications[$notification->id] = self::copyOf($notification);
    }

    public function delete(string $businessId, string $id): void
    {
        $this->journal->record('notifications.delete');

        if (($this->notifications[$id] ?? null)?->businessId !== $businessId) {
            throw StaffNotificationNotFound::withId($id);
        }

        unset($this->notifications[$id]);
    }

    private static function copyOf(StaffNotification $notification): StaffNotification
    {
        return StaffNotification::restore(
            id: $notification->id,
            businessId: $notification->businessId,
            recipientStaffMemberId: $notification->recipientStaffMemberId,
            type: $notification->type,
            appointmentId: $notification->appointmentId,
            readAt: $notification->readAt(),
            createdAt: $notification->createdAt,
        );
    }
}
