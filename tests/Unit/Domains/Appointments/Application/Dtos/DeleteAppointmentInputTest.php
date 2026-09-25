<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\DeleteAppointmentInput;
use App\Domains\Appointments\Exceptions\AppointmentNotFound;
use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;

dataset('identifiers no delete input could accept', [
    'empty' => '',
    'whitespace only' => '   ',
    'an integer key' => '42',
    'a word' => 'not-a-uuid',
    'a uuid missing a group' => '01930000-0000-7000-8000',
    'a uuid with trailing text' => AppointmentFixtures::APPOINTMENT_ID.' ',
]);

it('accepts a well formed appointment and account', function () {
    $input = new DeleteAppointmentInput(AppointmentFixtures::APPOINTMENT_ID, AppointmentFixtures::ACCOUNT_ID);

    expect(fn () => $input->validate())->not->toThrow(Throwable::class)
        ->and($input->appointmentId)->toBe(AppointmentFixtures::APPOINTMENT_ID)
        ->and($input->accountId)->toBe(AppointmentFixtures::ACCOUNT_ID);
});

it('refuses an appointment identifier that is no uuid', function (string $appointmentId) {
    expect(fn () => (new DeleteAppointmentInput($appointmentId, AppointmentFixtures::ACCOUNT_ID))->validate())
        ->toThrow(AppointmentNotFound::class);
})->with('identifiers no delete input could accept');

it('refuses an account that is no uuid', function (string $accountId) {
    expect(fn () => (new DeleteAppointmentInput(AppointmentFixtures::APPOINTMENT_ID, $accountId))->validate())
        ->toThrow(CalendarNotAccessible::class);
})->with('identifiers no delete input could accept');

it('refuses a malformed account as forbidden, with a failure the transport can classify', function () {
    $failure = null;

    try {
        (new DeleteAppointmentInput(AppointmentFixtures::APPOINTMENT_ID, 'nobody'))->validate();
    } catch (CalendarNotAccessible $refused) {
        $failure = $refused;
    }

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure?->errorCode())->toBe('business_not_accessible')
        ->and($failure?->kind())->toBe(DomainFailureKind::Forbidden);
});

it('judges the appointment before the account', function () {
    expect(fn () => (new DeleteAppointmentInput('not-a-uuid', 'nobody'))->validate())
        ->toThrow(AppointmentNotFound::class);
});
