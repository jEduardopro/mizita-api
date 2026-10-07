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

    private const EAGER_LOADED_RELATIONS = ['recipient', 'appointment'];

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
        $row = (new StaffNotificationModel)->forceFill([
            ...$this->attributesOf($notification),
            'updated_at' => $notification->createdAt,
        ]);

        StaffNotificationModel::query()->insertOrIgnore($row->getAttributes());
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

    /**
     * @return array<string, mixed>
     */
    private function attributesOf(StaffNotification $notification): array
    {
        $businessKey = $this->businessKeys->teamKeyFor($notification->businessId);

        return $this->mapper->toAttributes(
            $notification,
            $businessKey,
            $this->recipientKeyFor($businessKey, $notification->recipientStaffMemberId),
            $this->appointmentKeyFor($businessKey, $notification->appointmentId),
        );
    }

    private function recipientKeyFor(int $businessKey, string $staffMemberId): int
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
