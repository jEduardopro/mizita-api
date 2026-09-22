<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\ShowAppointmentPaymentInput;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Payments\PaymentFixtures;

it('accepts a well formed appointment identifier', function () {
    $input = new ShowAppointmentPaymentInput(PaymentFixtures::APPOINTMENT_ID);

    expect(fn () => $input->validate())->not->toThrow(Throwable::class)
        ->and($input->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID);
});

it('refuses an appointment identifier that is no uuid', function (string $appointmentId) {
    expect(fn () => (new ShowAppointmentPaymentInput($appointmentId))->validate())
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
        (new ShowAppointmentPaymentInput('nope'))->validate();
    } catch (PaymentAppointmentNotFound $caught) {
        $failure = $caught;
    }

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure?->errorCode())->toBe('payment_appointment_not_found');
});

it('is built by the controller from the route, never from a payload', function () {
    expect(method_exists(ShowAppointmentPaymentInput::class, 'fromRequest'))->toBeFalse();
});
