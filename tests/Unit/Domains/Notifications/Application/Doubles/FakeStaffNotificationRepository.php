<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Entities\NotificationEvent;
use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use DateTimeImmutable;
use Tests\Support\FakeTransactionManager;

final class FakeStaffNotificationRepository implements StaffNotificationRepository
{
    /**
     * @var array<string, StaffNotification>
     */
    private array $notifications = [];

    /**
     * @var list<array{event: NotificationEvent, deliveries: list<StaffNotification>, insideTransaction: ?bool}>
     */
    public array $recorded = [];

    /**
     * @var list<StaffNotification>
     */
    public array $markedAsRead = [];

    /**
     * @var list<array{businessId: string, id: string}>
     */
    public array $lookups = [];

    public function __construct(
        private readonly NotificationsJournal $journal = new NotificationsJournal,
        private readonly ?FakeTransactionManager $transactions = null,
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

    public function record(NotificationEvent $event, StaffNotification ...$deliveries): void
    {
        $this->journal->record('notifications.record');
        $this->recorded[] = [
            'event' => $event,
            'deliveries' => array_values($deliveries),
            'insideTransaction' => $this->transactions?->isRunning(),
        ];

        foreach ($deliveries as $delivery) {
            $this->notifications[$delivery->id] = self::copyOf($delivery);
        }
    }

    public function markAsRead(StaffNotification $notification): void
    {
        $this->journal->record('notifications.markAsRead');
        $this->markedAsRead[] = $notification;

        $readAt = $notification->readAt();
        $stored = $this->notifications[$notification->id] ?? null;

        if ($readAt === null || $stored === null || ! $stored->isUnread()) {
            return;
        }

        $this->notifications[$notification->id] = self::copyOf($stored, $readAt);
    }

    /**
     * @return list<StaffNotification>
     */
    public function all(): array
    {
        return array_values(array_map(static fn (StaffNotification $notification) => self::copyOf($notification), $this->notifications));
    }

    public function delete(string $businessId, string $id): void
    {
        $this->journal->record('notifications.delete');

        if (($this->notifications[$id] ?? null)?->businessId !== $businessId) {
            throw StaffNotificationNotFound::withId($id);
        }

        unset($this->notifications[$id]);
    }

    private static function copyOf(StaffNotification $notification, ?DateTimeImmutable $readAt = null): StaffNotification
    {
        return StaffNotification::restore(
            id: $notification->id,
            businessId: $notification->businessId,
            eventId: $notification->eventId,
            recipientStaffMemberId: $notification->recipientStaffMemberId,
            collapseKey: $notification->collapseKey,
            readAt: $readAt ?? $notification->readAt(),
            createdAt: $notification->createdAt,
        );
    }
}
