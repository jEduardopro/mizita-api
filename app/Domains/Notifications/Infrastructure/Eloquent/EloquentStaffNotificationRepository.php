<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent;

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use App\Domains\Notifications\Infrastructure\Eloquent\Mappers\StaffNotificationMapper;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\StaffNotificationModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EloquentStaffNotificationRepository implements StaffNotificationRepository
{
    private const PRIMARY_KEY = 'id';

    private const BUSINESSES_TABLE = 'businesses';

    private const EAGER_LOADED_RELATIONS = ['recipient', 'appointment', 'subject'];

    private const NO_ROWS = 0;

    public function __construct(
        private readonly StaffNotificationMapper $mapper,
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function findForBusiness(string $businessId, string $id): StaffNotification
    {
        return $this->mapper->toEntity($this->modelOrFail($businessId, $id), $businessId);
    }

    public function addOnce(StaffNotification $notification): void
    {
        $this->insertUnlessConflicting($this->rowOf($notification));
    }

    public function addOrRefreshUnread(StaffNotification $notification): void
    {
        $row = $this->rowOf($notification);

        if ($this->insertUnlessConflicting($row)) {
            return;
        }

        if ($this->refreshUnreadMatching($row)) {
            return;
        }

        $this->insertUnlessConflicting($row);
    }

    public function save(StaffNotification $notification): void
    {
        StaffNotificationModel::query()->updateOrCreate(
            ['uuid' => $notification->id],
            $this->attributesOf($notification),
        );
    }

    public function delete(string $businessId, string $id): void
    {
        $this->modelOrFail($businessId, $id)->delete();
    }

    private function rowOf(StaffNotification $notification): StaffNotificationModel
    {
        return (new StaffNotificationModel)->forceFill([
            ...$this->attributesOf($notification),
            'updated_at' => $notification->createdAt,
        ]);
    }

    private function insertUnlessConflicting(StaffNotificationModel $row): bool
    {
        return StaffNotificationModel::query()->insertOrIgnore($row->getAttributes()) > self::NO_ROWS;
    }

    private function refreshUnreadMatching(StaffNotificationModel $row): bool
    {
        $attributes = $row->getAttributes();

        return StaffNotificationModel::query()
            ->where('business_id', $attributes['business_id'])
            ->where('type', $attributes['type'])
            ->where('recipient_staff_member_id', $attributes['recipient_staff_member_id'])
            ->where('subject_staff_member_id', $attributes['subject_staff_member_id'])
            ->whereNull('read_at')
            ->update([
                'created_at' => $attributes['created_at'],
                'updated_at' => $attributes['updated_at'],
            ]) > self::NO_ROWS;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesOf(StaffNotification $notification): array
    {
        $businessKey = $this->businessKeys->teamKeyFor($notification->businessId);

        return $this->mapper->toAttributes(
            $notification,
            $businessKey,
            $this->staffMemberKeyFor($businessKey, $notification->recipientStaffMemberId),
            $this->appointmentKeyFor($businessKey, $notification->appointmentId),
            $this->subjectKeyFor($businessKey, $notification->subjectStaffMemberId),
        );
    }

    private function subjectKeyFor(int $businessKey, ?string $staffMemberId): ?int
    {
        if ($staffMemberId === null) {
            return null;
        }

        return $this->staffMemberKeyFor($businessKey, $staffMemberId);
    }

    private function staffMemberKeyFor(int $businessKey, string $staffMemberId): int
    {
        return (int) StaffMemberModel::query()
            ->withTrashed()
            ->where('business_id', $businessKey)
            ->where('uuid', $staffMemberId)
            ->valueOrFail(self::PRIMARY_KEY);
    }

    private function appointmentKeyFor(int $businessKey, ?string $appointmentId): ?int
    {
        if ($appointmentId === null) {
            return null;
        }

        return (int) AppointmentModel::query()
            ->withTrashed()
            ->where('business_id', $businessKey)
            ->where('uuid', $appointmentId)
            ->valueOrFail(self::PRIMARY_KEY);
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
