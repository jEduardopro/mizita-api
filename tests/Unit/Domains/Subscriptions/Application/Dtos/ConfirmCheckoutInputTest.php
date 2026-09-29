<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\ConfirmCheckoutInput;
use App\Domains\Subscriptions\Exceptions\CheckoutSessionNotFound;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

const CONFIRM_CHECKOUT_INPUT_BUSINESS_ID = '01930000-0000-7000-8000-00000000b801';

it('accepts a checkout session id', function (string $sessionId) {
    expect(fn () => (new ConfirmCheckoutInput(CONFIRM_CHECKOUT_INPUT_BUSINESS_ID, $sessionId))->validate())
        ->not->toThrow(Throwable::class);
})->with([
    'a test mode session' => 'cs_test_a1B2c3D4e5F6g7H8',
    'a live mode session' => 'cs_live_a1B2c3D4e5F6g7H8',
    'a single character after the prefix' => 'cs_a',
    'exactly the maximum length' => 'cs_'.str_repeat('a', ConfirmCheckoutInput::MAXIMUM_SESSION_ID_LENGTH - 3),
]);

it('rejects anything that is not a checkout session id', function (string $sessionId) {
    expect(fn () => (new ConfirmCheckoutInput(CONFIRM_CHECKOUT_INPUT_BUSINESS_ID, $sessionId))->validate())
        ->toThrow(CheckoutSessionNotFound::class);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'the bare prefix' => 'cs_',
    'an uppercase prefix' => 'CS_test_a1B2c3',
    'a payment intent id' => 'pi_a1B2c3',
    'a subscription id' => 'sub_a1B2c3',
    'a leading space' => ' cs_test_a1B2c3',
    'a trailing space' => 'cs_test_a1B2c3 ',
    'a trailing newline' => "cs_test_a1B2c3\n",
    'a hyphen' => 'cs_test-a1B2c3',
    'an accented letter' => 'cs_test_ñandú',
    'a path traversal' => 'cs_../../v1/customers',
    'a query string' => 'cs_test_a1B2c3?expand=customer',
    'one past the maximum length' => 'cs_'.str_repeat('a', ConfirmCheckoutInput::MAXIMUM_SESSION_ID_LENGTH - 2),
]);

it('refuses a malformed session as a session that was not found', function () {
    try {
        (new ConfirmCheckoutInput(CONFIRM_CHECKOUT_INPUT_BUSINESS_ID, 'pi_a1B2c3'))->validate();
    } catch (CheckoutSessionNotFound $failure) {
        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure->errorCode())->toBe('checkout_session_not_found')
            ->and($failure->kind())->toBe(DomainFailureKind::NotFound);

        return;
    }

    $this->fail('A malformed checkout session id was accepted.');
});
