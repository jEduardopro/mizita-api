<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarAlreadyConnected;
use App\Domains\Integrations\Exceptions\CalendarAppointmentNotFound;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationDenied;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;
use App\Domains\Integrations\Exceptions\CalendarBusinessNotFound;
use App\Domains\Integrations\Exceptions\CalendarConnectionNotFound;
use App\Domains\Integrations\Exceptions\CalendarOwnerNotFound;
use App\Domains\Integrations\Exceptions\CalendarScopeNotGranted;
use App\Domains\Integrations\Exceptions\ExternalCalendarUnavailable;
use App\Domains\Integrations\Exceptions\InvalidBusyWindow;
use App\Domains\Integrations\Exceptions\InvalidCalendarConnection;
use App\Domains\Integrations\Exceptions\InvalidCalendarEventLink;
use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

/**
 * @return array<string, array{DomainFailure, string, DomainFailureKind, string}>
 */
function integrationsDomainFailures(): array
{
    return [
        'a staff member already connected' => [
            CalendarAlreadyConnected::forStaffMember('staff-uuid'),
            'calendar_already_connected',
            DomainFailureKind::Conflict,
            'Staff member [staff-uuid] already has a connected calendar.',
        ],
        'an appointment nobody has' => [
            CalendarAppointmentNotFound::withId('appointment-uuid'),
            'appointment_not_found',
            DomainFailureKind::NotFound,
            'Appointment [appointment-uuid] was not found.',
        ],
        'a user who denied the authorization' => [
            CalendarAuthorizationDenied::byUser(),
            'calendar_authorization_denied',
            DomainFailureKind::Invalid,
            'The calendar authorization was denied by the user.',
        ],
        'a provider answering with an error' => [
            CalendarAuthorizationFailed::providerError('server_error'),
            'calendar_authorization_failed',
            DomainFailureKind::Invalid,
            'The calendar provider answered the authorization with [server_error].',
        ],
        'a callback with neither code nor error' => [
            CalendarAuthorizationFailed::missingCode(),
            'calendar_authorization_failed',
            DomainFailureKind::Invalid,
            'The calendar authorization carried neither a code nor an error.',
        ],
        'a code that could not be exchanged' => [
            CalendarAuthorizationFailed::exchangeFailed(),
            'calendar_authorization_failed',
            DomainFailureKind::Invalid,
            'The calendar authorization code could not be exchanged.',
        ],
        'a grant with no refresh token' => [
            CalendarAuthorizationFailed::missingRefreshToken(),
            'calendar_authorization_failed',
            DomainFailureKind::Invalid,
            'The calendar provider granted no refresh token.',
        ],
        'a grant with no account email' => [
            CalendarAuthorizationFailed::missingAccountEmail(),
            'calendar_authorization_failed',
            DomainFailureKind::Invalid,
            'The calendar provider did not disclose the account email.',
        ],
        'a dedicated calendar that could not be created' => [
            CalendarAuthorizationFailed::calendarNotProvisioned(),
            'calendar_authorization_failed',
            DomainFailureKind::Invalid,
            'The dedicated calendar could not be created.',
        ],
        'a revoked authorization' => [
            CalendarAuthorizationRevoked::forConnection('connection-uuid'),
            'calendar_authorization_revoked',
            DomainFailureKind::Conflict,
            'The authorization behind calendar connection [connection-uuid] was revoked.',
        ],
        'a callback with no state' => [
            CalendarAuthorizationStateInvalid::missing(),
            'calendar_authorization_state_invalid',
            DomainFailureKind::Invalid,
            'The calendar authorization carried no state.',
        ],
        'a state expired or already used' => [
            CalendarAuthorizationStateInvalid::expiredOrUsed(),
            'calendar_authorization_state_invalid',
            DomainFailureKind::Invalid,
            'The calendar authorization state has expired or was already used.',
        ],
        'a state issued to another account' => [
            CalendarAuthorizationStateInvalid::issuedToAnotherAccount(),
            'calendar_authorization_state_invalid',
            DomainFailureKind::Invalid,
            'The calendar authorization state was issued to another account.',
        ],
        'a business nobody has' => [
            CalendarBusinessNotFound::withId('business-uuid'),
            'business_not_found',
            DomainFailureKind::NotFound,
            'Business [business-uuid] was not found.',
        ],
        'a connection id nobody has' => [
            CalendarConnectionNotFound::withId('connection-uuid'),
            'calendar_connection_not_found',
            DomainFailureKind::NotFound,
            'Calendar connection [connection-uuid] was not found.',
        ],
        'a staff member with no connection' => [
            CalendarConnectionNotFound::forStaffMember('staff-uuid'),
            'calendar_connection_not_found',
            DomainFailureKind::NotFound,
            'Staff member [staff-uuid] has no calendar connection.',
        ],
        'an account that is no staff member of the business' => [
            CalendarOwnerNotFound::forAccount('account-uuid'),
            'staff_member_not_found',
            DomainFailureKind::NotFound,
            'Account [account-uuid] is not a staff member of this business.',
        ],
        'a staff member id nobody has' => [
            CalendarOwnerNotFound::withId('staff-uuid'),
            'staff_member_not_found',
            DomainFailureKind::NotFound,
            'Staff member [staff-uuid] was not found.',
        ],
        'a scope the user did not grant' => [
            CalendarScopeNotGranted::forScope('https://www.googleapis.com/auth/calendar.app.created'),
            'calendar_scope_not_granted',
            DomainFailureKind::Invalid,
            'The calendar scope [https://www.googleapis.com/auth/calendar.app.created] was not granted.',
        ],
        'an external calendar out of reach' => [
            ExternalCalendarUnavailable::forConnection('connection-uuid'),
            'external_calendar_unavailable',
            DomainFailureKind::Conflict,
            'The external calendar of connection [connection-uuid] could not be reached.',
        ],
        'an inverted busy window' => [
            InvalidBusyWindow::endsBeforeItStarts(),
            'invalid_busy_window',
            DomainFailureKind::Invalid,
            'A busy window must end after it starts.',
        ],
        'a connection with no account email' => [
            InvalidCalendarConnection::missingAccountEmail(),
            'invalid_calendar_connection',
            DomainFailureKind::Invalid,
            'A calendar connection needs the email of the connected account.',
        ],
        'a connection with no calendar' => [
            InvalidCalendarConnection::missingCalendar(),
            'invalid_calendar_connection',
            DomainFailureKind::Invalid,
            'A calendar connection needs the calendar it publishes to.',
        ],
        'a link pointing at no event' => [
            InvalidCalendarEventLink::missingEvent(),
            'invalid_calendar_event_link',
            DomainFailureKind::Invalid,
            'A calendar event link needs the external event it points to.',
        ],
        'an event ending before it starts' => [
            InvalidExternalCalendarEvent::endsBeforeItStarts(),
            'invalid_external_calendar_event',
            DomainFailureKind::Invalid,
            'An external calendar event must end after it starts.',
        ],
        'an event on a date that is no calendar date' => [
            InvalidExternalCalendarEvent::malformedDate('2026-02-30'),
            'invalid_external_calendar_event',
            DomainFailureKind::Invalid,
            'The external calendar event date [2026-02-30] is not a calendar date.',
        ],
    ];
}

it('answers with the stable error code the client is shown a sentence for', function (
    DomainFailure $failure,
    string $code,
) {
    expect($failure->errorCode())->toBe($code);
})->with(integrationsDomainFailures());

it('classifies the refusal so the edge knows which status to render', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
) {
    expect($failure->kind())->toBe($kind);
})->with(integrationsDomainFailures());

it('carries the interface the renderer is registered against', function (DomainFailure $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure)->toBeInstanceOf(Throwable::class);
})->with(integrationsDomainFailures());

it('says what it turned down', function (
    DomainFailure&Throwable $failure,
    string $code,
    DomainFailureKind $kind,
    string $message,
) {
    expect($failure->getMessage())->toBe($message);
})->with(integrationsDomainFailures());

it('has a sentence to show the caller in every locale', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
    string $message,
    string $locale,
) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][$code] ?? '')->toBeString()->not->toBe('');
})->with(integrationsDomainFailures())->with(['en', 'es']);

it('gives each refusal of the domain an error code of its own', function () {
    $codeByClass = [];

    foreach (integrationsDomainFailures() as [$failure, $code]) {
        $codeByClass[$failure::class] = $code;
    }

    expect($codeByClass)->toHaveCount(15)
        ->and(array_unique($codeByClass))->toHaveCount(15);
});

it('keeps the provider or transport error it translates as its previous', function (Closure $translate) {
    $cause = new RuntimeException('HTTP 503 from the provider');

    expect($translate($cause)->getPrevious())->toBe($cause);
})->with([
    'a failed exchange' => [fn (Throwable $cause) => CalendarAuthorizationFailed::exchangeFailed($cause)],
    'an unprovisioned calendar' => [fn (Throwable $cause) => CalendarAuthorizationFailed::calendarNotProvisioned($cause)],
    'a revoked authorization' => [fn (Throwable $cause) => CalendarAuthorizationRevoked::forConnection('connection-uuid', $cause)],
    'an unreachable calendar' => [fn (Throwable $cause) => ExternalCalendarUnavailable::forConnection('connection-uuid', $cause)],
    'a missing business' => [fn (Throwable $cause) => CalendarBusinessNotFound::withId('business-uuid', $cause)],
    'an account that is no staff member' => [fn (Throwable $cause) => CalendarOwnerNotFound::forAccount('account-uuid', $cause)],
]);
