<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use App\Domains\Notifications\Infrastructure\Http\Resources\StaffNotificationResource;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use Illuminate\Http\Request;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

/**
 * @return array<string, mixed>
 */
function staffNotificationBody(StaffNotificationData $data): array
{
    return (new StaffNotificationResource($data))->toArray(Request::create('/api/notifications'));
}

it('renders the notification and its neighbours under their uuids, with instants in atom', function () {
    $body = staffNotificationBody(StaffNotificationData::forReader(
        NotificationsFixtures::record(),
        NotificationReader::member(NotificationsFixtures::MEMBER_ID),
    ));

    expect($body)->toBe([
        'id' => NotificationsFixtures::NOTIFICATION_ID,
        'type' => 'appointment_booked',
        'read_at' => null,
        'created_at' => NotificationsFixtures::CREATED_AT,
        'can_mark_as_read' => true,
        'recipient' => [
            'id' => NotificationsFixtures::MEMBER_ID,
            'name' => NotificationsFixtures::MEMBER_NAME,
        ],
        'appointment' => [
            'id' => NotificationsFixtures::APPOINTMENT_ID,
            'starts_at' => NotificationsFixtures::STARTS_AT,
            'ends_at' => NotificationsFixtures::ENDS_AT,
            'service_name' => NotificationsFixtures::SERVICE_NAME,
            'reference_code' => NotificationsFixtures::REFERENCE_CODE,
        ],
        'customer' => [
            'id' => NotificationsFixtures::CUSTOMER_ID,
            'name' => NotificationsFixtures::CUSTOMER_NAME,
        ],
    ]);
});

it('renders the read time of a read notification in atom', function () {
    $body = staffNotificationBody(StaffNotificationData::forReader(
        NotificationsFixtures::record(readAt: NotificationsFixtures::READ_AT),
        NotificationReader::member(NotificationsFixtures::MEMBER_ID),
    ));

    expect($body['read_at'])->toBe(NotificationsFixtures::READ_AT)
        ->and($body['can_mark_as_read'])->toBeFalse();
});

it('renders a missing appointment and customer as null rather than dropping the keys', function () {
    $body = staffNotificationBody(StaffNotificationData::forReader(
        NotificationsFixtures::record(withAppointment: false, withCustomer: false),
        NotificationReader::member(NotificationsFixtures::MEMBER_ID),
    ));

    expect($body)->toHaveKey('appointment')
        ->and($body['appointment'])->toBeNull()
        ->and($body)->toHaveKey('customer')
        ->and($body['customer'])->toBeNull();
});
