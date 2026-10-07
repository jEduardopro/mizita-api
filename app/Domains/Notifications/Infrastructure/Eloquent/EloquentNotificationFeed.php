<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent;

use App\Domains\Notifications\Contracts\NotificationFeed;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use App\Domains\Notifications\ValueObjects\NotificationAudience;
use App\Domains\Notifications\ValueObjects\NotificationRecipient;
use App\Domains\Notifications\ValueObjects\NotificationRecord;
use App\Domains\Notifications\ValueObjects\NotificationStatus;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\NotifiedAppointment;
use App\Domains\Notifications\ValueObjects\NotifiedCustomer;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentNotificationFeed implements NotificationFeed
{
    private const NOTIFICATIONS_TABLE = 'staff_notifications';

    private const STORAGE_TIMEZONE = 'UTC';

    private const SELECTED_COLUMNS = [
        'staff_notifications.uuid as notification_id',
        'staff_notifications.type as type',
        'staff_notifications.read_at as read_at',
        'staff_notifications.created_at as created_at',
        'staff_members.uuid as recipient_id',
        'users.name as recipient_name',
        'appointments.uuid as appointment_id',
        'appointments.starts_at as starts_at',
        'appointments.ends_at as ends_at',
        'appointments.reference_code as reference_code',
        'services.name as service_name',
        'customers.uuid as customer_id',
        'customers.name as customer_name',
    ];

    /**
     * @return Paginated<NotificationRecord>
     */
    public function newestFirst(
        string $businessId,
        NotificationAudience $audience,
        NotificationStatus $status,
        Pagination $pagination,
    ): Paginated {
        $matching = $this->withStatus($this->forAudience($this->ofBusiness($businessId), $audience), $status);
        $total = (clone $matching)->count();

        $rows = $this->withDetails($matching)
            ->orderByDesc('staff_notifications.created_at')
            ->orderByDesc('staff_notifications.id')
            ->offset($pagination->offset())
            ->limit($pagination->perPage)
            ->get(self::SELECTED_COLUMNS);

        $records = [];

        foreach ($rows as $row) {
            $records[] = self::recordFrom($row);
        }

        return Paginated::of($records, $total, $pagination);
    }

    public function countUnread(string $businessId, NotificationAudience $audience): int
    {
        return $this->withStatus(
            $this->forAudience($this->ofBusiness($businessId), $audience),
            NotificationStatus::Unread,
        )->count();
    }

    public function find(string $businessId, string $notificationId): NotificationRecord
    {
        $row = $this->withDetails($this->ofBusiness($businessId))
            ->where('staff_notifications.uuid', $notificationId)
            ->first(self::SELECTED_COLUMNS);

        if (! $row instanceof stdClass) {
            throw StaffNotificationNotFound::withId($notificationId);
        }

        return self::recordFrom($row);
    }

    private function ofBusiness(string $businessId): Builder
    {
        return DB::table(self::NOTIFICATIONS_TABLE)
            ->join('businesses', 'businesses.id', '=', 'staff_notifications.business_id')
            ->join('staff_members', 'staff_members.id', '=', 'staff_notifications.recipient_staff_member_id')
            ->where('businesses.uuid', $businessId)
            ->whereNull('staff_notifications.deleted_at');
    }

    private function forAudience(Builder $query, NotificationAudience $audience): Builder
    {
        $recipientStaffMemberId = $audience->recipientFilter();

        if ($recipientStaffMemberId === null) {
            return $query;
        }

        return $query->where('staff_members.uuid', $recipientStaffMemberId);
    }

    private function withStatus(Builder $query, NotificationStatus $status): Builder
    {
        if (! $status->excludesRead()) {
            return $query;
        }

        return $query->whereNull('staff_notifications.read_at');
    }

    private function withDetails(Builder $query): Builder
    {
        return $query
            ->join('users', 'users.id', '=', 'staff_members.account_id')
            ->leftJoin('appointments', 'appointments.id', '=', 'staff_notifications.appointment_id')
            ->leftJoin('services', 'services.id', '=', 'appointments.service_id')
            ->leftJoin('customers', 'customers.id', '=', 'appointments.customer_id');
    }

    private static function recordFrom(stdClass $row): NotificationRecord
    {
        return new NotificationRecord(
            id: (string) $row->notification_id,
            type: NotificationType::from((string) $row->type),
            recipient: new NotificationRecipient(
                staffMemberId: (string) $row->recipient_id,
                name: (string) $row->recipient_name,
            ),
            appointment: self::appointmentFrom($row),
            customer: self::customerFrom($row),
            readAt: $row->read_at === null ? null : self::instantFrom($row->read_at),
            createdAt: self::instantFrom($row->created_at),
        );
    }

    private static function appointmentFrom(stdClass $row): ?NotifiedAppointment
    {
        if ($row->appointment_id === null) {
            return null;
        }

        return new NotifiedAppointment(
            appointmentId: (string) $row->appointment_id,
            startsAt: self::instantFrom($row->starts_at),
            endsAt: self::instantFrom($row->ends_at),
            serviceName: (string) $row->service_name,
            referenceCode: $row->reference_code === null ? null : (string) $row->reference_code,
        );
    }

    private static function customerFrom(stdClass $row): ?NotifiedCustomer
    {
        if ($row->customer_id === null) {
            return null;
        }

        return new NotifiedCustomer(
            customerId: (string) $row->customer_id,
            name: (string) $row->customer_name,
        );
    }

    private static function instantFrom(mixed $value): DateTimeImmutable
    {
        return (new DateTimeImmutable((string) $value))
            ->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE));
    }
}
