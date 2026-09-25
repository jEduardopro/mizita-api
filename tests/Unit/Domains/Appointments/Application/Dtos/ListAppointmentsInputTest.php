<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\ListAppointmentsInput;
use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Domains\Appointments\Exceptions\InvalidCalendarRange;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;

describe('the payload it reads', function () {
    it('reads the range and takes the account from the parameter', function () {
        $input = ListAppointmentsInput::fromRequest(
            ['from' => AppointmentFixtures::RANGE_FROM, 'to' => AppointmentFixtures::RANGE_TO],
            AppointmentFixtures::ACCOUNT_ID,
        );

        expect($input->from)->toBe(AppointmentFixtures::RANGE_FROM)
            ->and($input->to)->toBe(AppointmentFixtures::RANGE_TO)
            ->and($input->accountId)->toBe(AppointmentFixtures::ACCOUNT_ID);
    });

    it('never reads the account from a key the caller could set', function () {
        $input = ListAppointmentsInput::fromRequest([
            'from' => AppointmentFixtures::RANGE_FROM,
            'to' => AppointmentFixtures::RANGE_TO,
            'account_id' => AppointmentFixtures::SECOND_ACCOUNT_ID,
        ], AppointmentFixtures::ACCOUNT_ID);

        expect($input->accountId)->toBe(AppointmentFixtures::ACCOUNT_ID);
    });

    it('survives a payload with every key missing and turns it into a domain failure', function () {
        $input = ListAppointmentsInput::fromRequest([], AppointmentFixtures::ACCOUNT_ID);

        expect($input->from)->toBe('')
            ->and($input->to)->toBe('')
            ->and(fn () => $input->validate())->toThrow(InvalidCalendarRange::class);
    });
});

describe('the account it validates', function () {
    it('accepts a well formed range and account', function () {
        expect(fn () => AppointmentFixtures::listInput()->validate())->not->toThrow(Throwable::class);
    });

    it('refuses an account that is no uuid', function (string $accountId) {
        expect(fn () => AppointmentFixtures::listInput(accountId: $accountId)->validate())
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
            AppointmentFixtures::listInput(accountId: 'nobody')->validate();
        } catch (CalendarNotAccessible $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('business_not_accessible')
            ->and($failure?->kind())->toBe(DomainFailureKind::Forbidden);
    });

    it('judges the range before the account', function () {
        expect(fn () => AppointmentFixtures::listInput(from: 'yesterday', accountId: 'nobody')->validate())
            ->toThrow(InvalidCalendarRange::class);
    });
});
