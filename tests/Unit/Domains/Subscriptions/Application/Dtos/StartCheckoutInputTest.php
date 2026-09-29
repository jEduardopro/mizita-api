<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\StartCheckoutInput;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPlan;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

const START_CHECKOUT_INPUT_BUSINESS_ID = '01930000-0000-7000-8000-00000000b701';

const START_CHECKOUT_INPUT_PLAN_ID = '01930000-0000-7000-8000-00000000c701';

it('reads the plan from the payload and the business from the caller', function () {
    $input = StartCheckoutInput::fromRequest(
        ['plan_id' => START_CHECKOUT_INPUT_PLAN_ID, 'business_id' => '01930000-0000-7000-8000-0000000000ff'],
        START_CHECKOUT_INPUT_BUSINESS_ID,
    );

    expect($input->planId)->toBe(START_CHECKOUT_INPUT_PLAN_ID)
        ->and($input->businessId)->toBe(START_CHECKOUT_INPUT_BUSINESS_ID);
});

it('accepts a well formed plan uuid', function (string $planId) {
    expect(fn () => StartCheckoutInput::fromRequest(['plan_id' => $planId], START_CHECKOUT_INPUT_BUSINESS_ID)->validate())
        ->not->toThrow(Throwable::class);
})->with([
    'lowercase' => START_CHECKOUT_INPUT_PLAN_ID,
    'uppercase' => '01930000-0000-7000-8000-00000000C701',
]);

it('survives a payload with no plan and refuses it as a domain failure', function () {
    $input = StartCheckoutInput::fromRequest([], START_CHECKOUT_INPUT_BUSINESS_ID);

    expect($input->planId)->toBe('')
        ->and(fn () => $input->validate())->toThrow(InvalidSubscriptionPlan::class);
});

it('rejects a plan that is not a uuid', function (mixed $planId) {
    expect(fn () => StartCheckoutInput::fromRequest(['plan_id' => $planId], START_CHECKOUT_INPUT_BUSINESS_ID)->validate())
        ->toThrow(InvalidSubscriptionPlan::class);
})->with([
    'null' => [null],
    'empty' => [''],
    'spaces' => ['   '],
    'the plan key' => ['complete'],
    'an int id' => [1],
    'a numeric string' => ['42'],
    'a stripe price id' => ['price_CompleteMonthly'],
    'without hyphens' => ['019300000000700080000000000c0701'],
    'one digit short' => ['01930000-0000-7000-8000-00000000c70'],
    'one digit long' => ['01930000-0000-7000-8000-00000000c7011'],
    'not hexadecimal' => ['01930000-0000-7000-8000-00000000g701'],
    'wrapped in braces' => ['{01930000-0000-7000-8000-00000000c701}'],
    'surrounded by spaces' => [' 01930000-0000-7000-8000-00000000c701 '],
    'with a trailing newline' => ["01930000-0000-7000-8000-00000000c701\n"],
]);

it('refuses a malformed plan as an invalid domain failure', function () {
    try {
        (new StartCheckoutInput(START_CHECKOUT_INPUT_BUSINESS_ID, 'complete'))->validate();
    } catch (InvalidSubscriptionPlan $failure) {
        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure->errorCode())->toBe('invalid_subscription_plan')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

        return;
    }

    $this->fail('A malformed plan id was accepted.');
});
