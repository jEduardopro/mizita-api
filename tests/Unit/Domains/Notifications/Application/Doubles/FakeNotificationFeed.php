<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

use App\Domains\Notifications\Contracts\NotificationFeed;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use App\Domains\Notifications\ValueObjects\NotificationAudience;
use App\Domains\Notifications\ValueObjects\NotificationRecord;
use App\Domains\Notifications\ValueObjects\NotificationStatus;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;

final class FakeNotificationFeed implements NotificationFeed
{
    /**
     * @var array<string, array<string, NotificationRecord>>
     */
    private array $recordsByBusiness = [];

    /**
     * @var list<array{businessId: string, recipientFilter: ?string, status: NotificationStatus, page: int, perPage: int}>
     */
    public array $queries = [];

    /**
     * @var list<array{businessId: string, recipientFilter: ?string}>
     */
    public array $unreadCounts = [];

    /**
     * @var list<array{businessId: string, notificationId: string}>
     */
    public array $finds = [];

    public function __construct(
        private readonly NotificationsJournal $journal = new NotificationsJournal,
        private readonly ?FakeStaffNotificationRepository $writes = null,
    ) {}

    public function add(string $businessId, NotificationRecord ...$records): self
    {
        foreach ($records as $record) {
            $this->recordsByBusiness[$businessId][$record->id] = $record;
        }

        return $this;
    }

    public function newestFirst(
        string $businessId,
        NotificationAudience $audience,
        NotificationStatus $status,
        Pagination $pagination,
    ): Paginated {
        $this->journal->record('feed.newestFirst');
        $this->queries[] = [
            'businessId' => $businessId,
            'recipientFilter' => $audience->recipientFilter(),
            'status' => $status,
            'page' => $pagination->page,
            'perPage' => $pagination->perPage,
        ];

        $matching = array_values(array_filter(
            $this->recordsOf($businessId),
            static fn (NotificationRecord $record): bool => ($audience->recipientFilter() === null
                    || $record->recipient->staffMemberId === $audience->recipientFilter())
                && (! $status->excludesRead() || $record->readAt === null),
        ));

        return Paginated::of(
            array_slice($matching, $pagination->offset(), $pagination->perPage),
            count($matching),
            $pagination,
        );
    }

    public function countUnread(string $businessId, NotificationAudience $audience): int
    {
        $this->journal->record('feed.countUnread');
        $this->unreadCounts[] = ['businessId' => $businessId, 'recipientFilter' => $audience->recipientFilter()];

        return count(array_filter(
            $this->recordsOf($businessId),
            static fn (NotificationRecord $record): bool => $record->readAt === null
                && ($audience->recipientFilter() === null
                    || $record->recipient->staffMemberId === $audience->recipientFilter()),
        ));
    }

    public function find(string $businessId, string $notificationId): NotificationRecord
    {
        $this->journal->record('feed.find');
        $this->finds[] = ['businessId' => $businessId, 'notificationId' => $notificationId];

        $record = $this->recordsByBusiness[$businessId][$notificationId]
            ?? throw StaffNotificationNotFound::withId($notificationId);

        return $this->withPersistedReadTime($record);
    }

    /**
     * @return list<NotificationRecord>
     */
    private function recordsOf(string $businessId): array
    {
        return array_values(array_map(
            fn (NotificationRecord $record): NotificationRecord => $this->withPersistedReadTime($record),
            $this->recordsByBusiness[$businessId] ?? [],
        ));
    }

    private function withPersistedReadTime(NotificationRecord $record): NotificationRecord
    {
        $persisted = $this->writes?->stored($record->id);

        if ($persisted === null) {
            return $record;
        }

        return new NotificationRecord(
            id: $record->id,
            type: $record->type,
            recipient: $record->recipient,
            appointment: $record->appointment,
            customer: $record->customer,
            readAt: $persisted->readAt(),
            createdAt: $record->createdAt,
        );
    }
}
