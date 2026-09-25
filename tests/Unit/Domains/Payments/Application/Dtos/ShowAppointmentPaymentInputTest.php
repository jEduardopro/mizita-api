<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\ShowAppointmentPaymentInput;
use App\Domains\Payments\Exceptions\InvalidPaymentActor;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Payments\PaymentFixtures;

it('accepts a well formed appointment identifier', function () {
    $input = new ShowAppointmentPaymentInput(PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

    expect(fn () => $input->validate())->not->toThrow(Throwable::class)
        ->and($input->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID)
        ->and($input->actorAccountId)->toBe(PaymentFixtures::ACTOR_ID);
});

it('refuses an appointment identifier that is no uuid', function (string $appointmentId) {
    expect(fn () => (new ShowAppointmentPaymentInput($appointmentId, PaymentFixtures::ACTOR_ID))->validate())
        ->toThrow(PaymentAppointmentNotFound::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a sequential int' => '42',
    'a word' => 'not-a-uuid',
    'a uuid missing a group' => '01930000-0000-7000-8000',
    'a uuid with a non hex digit' => '01930000-0000-7000-8000-0000000000zz',
    'a uuid with trailing text' => PaymentFixtures::APPOINTMENT_ID.' ',
]);

it('refuses with a failure the transport can classify', function () {
    $failure = null;

    try {
        (new ShowAppointmentPaymentInput('nope', PaymentFixtures::ACTOR_ID))->validate();
    } catch (PaymentAppointmentNotFound $caught) {
        $failure = $caught;
    }

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure?->errorCode())->toBe('payment_appointment_not_found');
});

it('is built by the controller from the route, never from a payload', function () {
    expect(method_exists(ShowAppointmentPaymentInput::class, 'fromRequest'))->toBeFalse();
});

it('refuses an actor that is no uuid', function (string $actorAccountId) {
    expect(fn () => (new ShowAppointmentPaymentInput(PaymentFixtures::APPOINTMENT_ID, $actorAccountId))->validate())
        ->toThrow(InvalidPaymentActor::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a sequential int' => '42',
    'a word' => 'the-owner',
    'a uuid with trailing text' => PaymentFixtures::ACTOR_ID.' ',
]);

it('refuses a malformed actor as invalid, with a failure the transport can classify', function () {
    $failure = null;

    try {
        (new ShowAppointmentPaymentInput(PaymentFixtures::APPOINTMENT_ID, 'the-owner'))->validate();
    } catch (InvalidPaymentActor $caught) {
        $failure = $caught;
    }

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure?->errorCode())->toBe('invalid_payment_actor')
        ->and($failure?->kind())->toBe(DomainFailureKind::Invalid);
});

it('judges the appointment before the actor', function () {
    expect(fn () => (new ShowAppointmentPaymentInput('nope', 'the-owner'))->validate())
        ->toThrow(PaymentAppointmentNotFound::class);
});
