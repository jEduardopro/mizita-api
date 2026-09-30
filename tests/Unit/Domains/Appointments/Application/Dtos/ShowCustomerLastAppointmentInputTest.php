<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;

dataset('identifiers no last appointment input could accept', [
    'empty' => '',
    'whitespace only' => '   ',
    'an integer key' => '42',
    'a word' => 'not-a-uuid',
    'a uuid missing a group' => '01930000-0000-7000-8000',
    'a uuid with trailing text' => AppointmentFixtures::CUSTOMER_ID.' ',
]);

it('accepts a well formed customer and account', function () {
    $input = AppointmentFixtures::showCustomerLastInput();

    expect(fn () => $input->validate())->not->toThrow(Throwable::class)
        ->and($input->customerId)->toBe(AppointmentFixtures::CUSTOMER_ID)
        ->and($input->accountId)->toBe(AppointmentFixtures::ACCOUNT_ID);
});

it('accepts a uuid written in upper case', function () {
    $input = AppointmentFixtures::showCustomerLastInput(
        customerId: strtoupper(AppointmentFixtures::CUSTOMER_ID),
        accountId: strtoupper(AppointmentFixtures::ACCOUNT_ID),
    );

    expect(fn () => $input->validate())->not->toThrow(Throwable::class);
});

it('refuses a customer identifier that is no uuid', function (string $customerId) {
    expect(fn () => AppointmentFixtures::showCustomerLastInput(customerId: $customerId)->validate())
        ->toThrow(AppointmentCustomerNotFound::class);
})->with('identifiers no last appointment input could accept');

it('refuses an account that is no uuid', function (string $accountId) {
    expect(fn () => AppointmentFixtures::showCustomerLastInput(accountId: $accountId)->validate())
        ->toThrow(CalendarNotAccessible::class);
})->with('identifiers no last appointment input could accept');

it('refuses a malformed customer as not found, with a failure the transport can classify', function () {
    $failure = null;

    try {
        AppointmentFixtures::showCustomerLastInput(customerId: 'not-a-uuid')->validate();
    } catch (AppointmentCustomerNotFound $refused) {
        $failure = $refused;
    }

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure?->errorCode())->toBe('appointment_customer_not_found')
        ->and($failure?->kind())->toBe(DomainFailureKind::NotFound);
});

it('refuses a malformed account as forbidden, with a failure the transport can classify', function () {
    $failure = null;

    try {
        AppointmentFixtures::showCustomerLastInput(accountId: 'nobody')->validate();
    } catch (CalendarNotAccessible $refused) {
        $failure = $refused;
    }

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure?->errorCode())->toBe('business_not_accessible')
        ->and($failure?->kind())->toBe(DomainFailureKind::Forbidden);
});

it('judges the customer before the account', function () {
    expect(fn () => AppointmentFixtures::showCustomerLastInput(customerId: 'not-a-uuid', accountId: 'nobody')->validate())
        ->toThrow(AppointmentCustomerNotFound::class);
});
