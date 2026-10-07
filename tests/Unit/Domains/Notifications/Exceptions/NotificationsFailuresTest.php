<?php

declare(strict_types=1);

use App\Domains\Notifications\Exceptions\InvalidNotificationScope;
use App\Domains\Notifications\Exceptions\InvalidNotificationStatus;
use App\Domains\Notifications\Exceptions\NotificationNotAddressedToReader;
use App\Domains\Notifications\Exceptions\NotificationsNotAccessible;
use App\Domains\Notifications\Exceptions\NotifiedAppointmentNotFound;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use App\Domains\Notifications\Exceptions\TeamNotificationsRequireOwner;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

/**
 * @return array<string, array{DomainFailure, string, DomainFailureKind}>
 */
function notificationsFailures(): array
{
    $id = '01930000-0000-7000-8000-000000000201';

    return [
        'a scope nothing serves' => [
            InvalidNotificationScope::unknown('everyone'),
            'invalid_notification_scope',
            DomainFailureKind::Invalid,
        ],
        'a status nothing serves' => [
            InvalidNotificationStatus::unknown('archived'),
            'invalid_notification_status',
            DomainFailureKind::Invalid,
        ],
        'a notification read by somebody it is not addressed to' => [
            NotificationNotAddressedToReader::forStaffMember($id, $id),
            'notification_not_addressed_to_reader',
            DomainFailureKind::Forbidden,
        ],
        'an account with no membership in the business' => [
            NotificationsNotAccessible::forAccount($id),
            'business_not_accessible',
            DomainFailureKind::Forbidden,
        ],
        'an appointment that is gone' => [
            NotifiedAppointmentNotFound::withId($id),
            'notified_appointment_not_found',
            DomainFailureKind::NotFound,
        ],
        'a notification the caller cannot see' => [
            StaffNotificationNotFound::withId($id),
            'staff_notification_not_found',
            DomainFailureKind::NotFound,
        ],
        'a team member asking for the whole team' => [
            TeamNotificationsRequireOwner::forStaffMember($id),
            'team_notifications_require_owner',
            DomainFailureKind::Forbidden,
        ],
    ];
}

/**
 * @return list<string>
 */
function declaredNotificationsFailures(): array
{
    return array_map(
        static fn (string $path) => basename($path, '.php'),
        glob(dirname(__DIR__, 5).'/app/Domains/Notifications/Exceptions/*.php') ?: [],
    );
}

it('answers with the stable error code the client is shown a sentence for', function (DomainFailure $failure, string $code) {
    expect($failure->errorCode())->toBe($code);
})->with(notificationsFailures());

it('classifies the refusal so the edge knows which status to render', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
) {
    expect($failure->kind())->toBe($kind);
})->with(notificationsFailures());

it('carries the interface the renderer is registered against', function (DomainFailure $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure)->toBeInstanceOf(Throwable::class);
})->with(notificationsFailures());

it('has a sentence to show the caller in every locale', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
    string $locale,
) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][$code] ?? '')->toBeString()->not->toBe('');
})->with(notificationsFailures())->with(['en', 'es']);

it('covers every refusal the domain declares', function () {
    $covered = array_map(
        static fn (array $failure) => (new ReflectionClass($failure[0]))->getShortName(),
        array_values(notificationsFailures()),
    );

    expect($covered)->toEqualCanonicalizing(declaredNotificationsFailures());
});

it('keeps the membership lookup that failed as the cause of an inaccessible business', function () {
    $cause = new RuntimeException('Staff member not found.');

    expect(NotificationsNotAccessible::forAccount('the-account', $cause)->getPrevious())->toBe($cause);
});

it('names the notification and the reader it turned down', function () {
    expect(NotificationNotAddressedToReader::forStaffMember('the-notification', 'the-reader')->getMessage())
        ->toBe('Staff notification [the-notification] is not addressed to staff member [the-reader].');
});
