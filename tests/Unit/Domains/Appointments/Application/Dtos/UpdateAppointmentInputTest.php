<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\UpdateAppointmentInput;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentNotFound;
use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;

describe('the payload it reads', function () {
    it('takes the appointment and the account from the parameters, never from the body', function () {
        $input = UpdateAppointmentInput::fromRequest(
            AppointmentFixtures::createPayload([
                'appointment_id' => AppointmentFixtures::SECOND_APPOINTMENT_ID,
                'account_id' => AppointmentFixtures::SECOND_ACCOUNT_ID,
            ]),
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::ACCOUNT_ID,
        );

        expect($input->appointmentId)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->and($input->accountId)->toBe(AppointmentFixtures::ACCOUNT_ID)
            ->and($input->staffMemberId)->toBe(AppointmentFixtures::STAFF_ID);
    });

    it('survives a payload with every key missing and turns it into a domain failure', function () {
        $input = UpdateAppointmentInput::fromRequest([], AppointmentFixtures::APPOINTMENT_ID, AppointmentFixtures::ACCOUNT_ID);

        expect($input->customerId)->toBe('')
            ->and($input->endsAt)->toBeNull()
            ->and(fn () => $input->validate())->toThrow(AppointmentCustomerNotFound::class);
    });
});

describe('the account it validates', function () {
    it('accepts a payload every rule agrees with', function () {
        expect(fn () => AppointmentFixtures::updateInput()->validate())->not->toThrow(Throwable::class);
    });

    it('refuses an account that is no uuid', function (string $accountId) {
        expect(fn () => AppointmentFixtures::updateInput(accountId: $accountId)->validate())
            ->toThrow(CalendarNotAccessible::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'an integer key' => '42',
        'a word' => 'not-a-uuid',
        'a uuid with trailing text' => AppointmentFixtures::ACCOUNT_ID.' ',
    ]);

    it('refuses a malformed account as forbidden, with a failure the transport can classify', function () {
        $failure = null;

        try {
            AppointmentFixtures::updateInput(accountId: 'nobody')->validate();
        } catch (CalendarNotAccessible $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('business_not_accessible')
            ->and($failure?->kind())->toBe(DomainFailureKind::Forbidden);
    });

    it('judges the appointment before the account', function () {
        expect(fn () => AppointmentFixtures::updateInput(appointmentId: 'nothing', accountId: 'nobody')->validate())
            ->toThrow(AppointmentNotFound::class);
    });
});
