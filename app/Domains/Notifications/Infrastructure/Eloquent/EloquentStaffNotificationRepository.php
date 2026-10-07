<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent;

use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Entities\NotificationEvent;
use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use App\Domains\Notifications\Infrastructure\Eloquent\Mappers\NotificationEventMapper;
use App\Domains\Notifications\Infrastructure\Eloquent\Mappers\StaffNotificationMapper;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\NotificationEventModel;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\StaffNotificationModel;
use App\Domains\Notifications\ValueObjects\NotificationSubject;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Shared\Contracts\BusinessTeamKey;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EloquentStaffNotificationRepository implements StaffNotificationRepository
{
    private const PRIMARY_KEY = 'id';

    private const BUSINESSES_TABLE = 'businesses';

    private const EAGER_LOADED_RELATIONS = ['recipient', 'event'];

    private const NO_ROWS = 0;

    public function __construct(
        private readonly StaffNotificationMapper $deliveryMapper,
        private readonly NotificationEventMapper $eventMapper,
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function findForBusiness(string $businessId, string $id): StaffNotification
    {
        return $this->deliveryMapper->toEntity($this->modelOrFail($businessId, $id), $businessId);
    }

    public function record(NotificationEvent $event, StaffNotification ...$deliveries): void
    {
        if (! $this->insertUnlessConflicting(NotificationEventModel::query(), $this->eventRowOf($event))) {
            return;
        }

        $eventKey = $this->eventKeyFor($event->id);

        foreach ($deliveries as $delivery) {
            $this->deliver($this->deliveryRowOf($delivery, $eventKey));
        }
    }

    public function markAsRead(StaffNotification $notification): void
    {
        $readAt = $notification->readAt();

        if ($readAt === null) {
            return;
        }

        StaffNotificationModel::query()
            ->where('uuid', $notification->id)
            ->where('business_id', $this->businessKeys->teamKeyFor($notification->businessId))
            ->whereNull('read_at')
            ->update($this->readStateRowOf($readAt));
    }

    public function delete(string $businessId, string $id): void
    {
        $this->modelOrFail($businessId, $id)->delete();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function deliver(array $row): void
    {
        if ($this->insertUnlessConflicting(StaffNotificationModel::query(), $row)) {
            return;
        }

        if ($this->collapseIntoUnreadDelivery($row)) {
            return;
        }

        $this->insertUnlessConflicting(StaffNotificationModel::query(), $row);
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  array<string, mixed>  $row
     */
    private function insertUnlessConflicting(Builder $query, array $row): bool
    {
        return $query->insertOrIgnore($row) > self::NO_ROWS;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function collapseIntoUnreadDelivery(array $row): bool
    {
        return StaffNotificationModel::query()
            ->where('business_id', $row['business_id'])
            ->where('recipient_staff_member_id', $row['recipient_staff_member_id'])
            ->where('collapse_key', $row['collapse_key'])
            ->whereNull('read_at')
            ->update([
                'notification_event_id' => $row['notification_event_id'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]) > self::NO_ROWS;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRowOf(NotificationEvent $event): array
    {
        $attributes = $this->eventMapper->toAttributes(
            $event,
            $this->businessKeys->teamKeyFor($event->businessId),
            $this->subjectKeyFor($event->subject),
        );

        return (new NotificationEventModel)->forceFill($attributes)->getAttributes();
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryRowOf(StaffNotification $delivery, int $eventKey): array
    {
        return (new StaffNotificationModel)->forceFill([
            ...$this->deliveryAttributesOf($delivery, $eventKey),
            'updated_at' => $delivery->createdAt,
        ])->getAttributes();
    }

    /**
     * @return array<string, mixed>
     */
    private function readStateRowOf(DateTimeImmutable $readAt): array
    {
        return (new StaffNotificationModel)->forceFill([
            'read_at' => $readAt,
            'updated_at' => $readAt,
        ])->getAttributes();
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryAttributesOf(StaffNotification $delivery, int $eventKey): array
    {
        $businessKey = $this->businessKeys->teamKeyFor($delivery->businessId);

        return $this->deliveryMapper->toAttributes(
            $delivery,
            $businessKey,
            $eventKey,
            $this->staffMemberKeyFor($businessKey, $delivery->recipientStaffMemberId),
        );
    }

    private function eventKeyFor(string $eventId): int
    {
        return (int) NotificationEventModel::query()
            ->withTrashed()
            ->where('uuid', $eventId)
            ->valueOrFail(self::PRIMARY_KEY);
    }

    private function staffMemberKeyFor(int $businessKey, string $staffMemberId): int
    {
        return (int) StaffMemberModel::query()
            ->withTrashed()
            ->where('business_id', $businessKey)
            ->where('uuid', $staffMemberId)
            ->valueOrFail(self::PRIMARY_KEY);
    }

    private function subjectKeyFor(NotificationSubject $subject): int
    {
        return (int) $this->subjectModel($subject)::query()
            ->withoutGlobalScopes()
            ->where('uuid', $subject->id)
            ->valueOrFail(self::PRIMARY_KEY);
    }

    /**
     * @return class-string<Model>
     */
    private function subjectModel(NotificationSubject $subject): string
    {
        /** @var class-string<Model>|null $model */
        $model = Relation::getMorphedModel($subject->type->value);

        if ($model === null) {
            throw (new ModelNotFoundException)->setModel($subject->type->value, [$subject->id]);
        }

        return $model;
    }

    /**
     * @throws StaffNotificationNotFound
     */
    private function modelOrFail(string $businessId, string $id): StaffNotificationModel
    {
        $model = StaffNotificationModel::query()
            ->with(self::EAGER_LOADED_RELATIONS)
            ->whereIn(
                'business_id',
                static fn (QueryBuilder $query) => $query
                    ->select(self::PRIMARY_KEY)
                    ->from(self::BUSINESSES_TABLE)
                    ->where('uuid', $businessId),
            )
            ->where('uuid', $id)
            ->first();

        if ($model === null) {
            throw StaffNotificationNotFound::withId($id);
        }

        return $model;
    }
}
