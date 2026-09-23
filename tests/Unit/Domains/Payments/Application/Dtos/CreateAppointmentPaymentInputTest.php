<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\CreateAppointmentPaymentInput;
use App\Domains\Payments\Application\Dtos\DiscountInput;
use App\Domains\Payments\Application\Dtos\PaymentAddOnInput;
use App\Domains\Payments\Exceptions\InvalidPaymentActor;
use App\Domains\Payments\Exceptions\InvalidPaymentDiscount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemAmount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemName;
use App\Domains\Payments\Exceptions\InvalidTransactionAmount;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\Exceptions\TooManyPaymentItems;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentItemName;
use Tests\Support\Payments\PaymentFixtures;

describe('reading an untrusted payload', function () {
    it('assembles itself and its children from the payload a form request would have let through', function () {
        $input = CreateAppointmentPaymentInput::fromRequest(PaymentFixtures::createPayload([
            'add_ons' => [
                ['name' => 'Beard trim', 'amount_cents' => 12_000],
                ['name' => 'Hot towel', 'amount_cents' => 3_000],
            ],
            'discount' => ['type' => 'percentage', 'value' => 1_000],
        ]), PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID)
            ->and($input->actorAccountId)->toBe(PaymentFixtures::ACTOR_ID)
            ->and($input->paymentMethodId)->toBe(PaymentFixtures::CASH_METHOD_ID)
            ->and($input->amountCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($input->addOns)->toHaveCount(2)
            ->and($input->addOns[0])->toBeInstanceOf(PaymentAddOnInput::class)
            ->and($input->addOns[0]->name)->toBe('Beard trim')
            ->and($input->addOns[0]->amountCents)->toBe(12_000)
            ->and($input->addOns[1]->name)->toBe('Hot towel')
            ->and($input->discount)->toBeInstanceOf(DiscountInput::class)
            ->and($input->discount?->type)->toBe('percentage')
            ->and($input->discount?->value)->toBe(1_000)
            ->and(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('takes the appointment from the route and never from the body', function () {
        $input = CreateAppointmentPaymentInput::fromRequest(PaymentFixtures::createPayload([
            'appointment_id' => PaymentFixtures::SECOND_APPOINTMENT_ID,
        ]), PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID);
    });

    it('takes the actor from the authenticated caller and never from the body', function () {
        $input = CreateAppointmentPaymentInput::fromRequest(PaymentFixtures::createPayload([
            'actor_account_id' => PaymentFixtures::OTHER_ACTOR_ID,
            'account_id' => PaymentFixtures::OTHER_ACTOR_ID,
        ]), PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->actorAccountId)->toBe(PaymentFixtures::ACTOR_ID);
    });

    it('carries no key for the service line, so a caller cannot price it', function () {
        $input = CreateAppointmentPaymentInput::fromRequest(PaymentFixtures::createPayload([
            'items' => [['name' => 'Free haircut', 'amount_cents' => 0]],
            'service_name' => 'Free haircut',
            'service_price_cents' => 0,
            'subtotal_cents' => 0,
            'total_cents' => 0,
        ]), PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        expect(array_keys(get_object_vars($input)))
            ->toBe(['appointmentId', 'addOns', 'discount', 'paymentMethodId', 'amountCents', 'actorAccountId'])
            ->and($input->addOns)->toBe([]);
    });

    it('survives a payload with no keys at all and refuses it on validate', function () {
        $input = CreateAppointmentPaymentInput::fromRequest([], PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->addOns)->toBe([])
            ->and($input->discount)->toBeNull()
            ->and($input->paymentMethodId)->toBe('')
            ->and($input->amountCents)->toBe(0)
            ->and(fn () => $input->validate())->toThrow(PaymentMethodNotFound::class);
    });

    it('survives a payload whose values are all the wrong type', function () {
        $input = CreateAppointmentPaymentInput::fromRequest([
            'add_ons' => 'not an array of add-ons',
            'discount' => 42,
            'payment_method_id' => ['an', 'array'],
            'amount_cents' => 'not a number',
        ], PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->addOns)->toBe([])
            ->and($input->discount)->toBeNull()
            ->and($input->paymentMethodId)->toBe('')
            ->and($input->amountCents)->toBe(0)
            ->and(fn () => $input->validate())->toThrow(PaymentMethodNotFound::class);
    });

    it('turns an add-on that is not even an array into one it can rule on', function () {
        $input = CreateAppointmentPaymentInput::fromRequest(PaymentFixtures::createPayload([
            'add_ons' => ['a bare string', null, 7],
        ]), PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->addOns)->toHaveCount(3)
            ->and($input->addOns[0])->toBeInstanceOf(PaymentAddOnInput::class)
            ->and($input->addOns[0]->name)->toBe('')
            ->and(fn () => $input->validate())->toThrow(InvalidPaymentItemName::class);
    });

    it('reindexes add-ons that arrived under string keys', function () {
        $input = CreateAppointmentPaymentInput::fromRequest(PaymentFixtures::createPayload([
            'add_ons' => ['second' => ['name' => 'Hot towel', 'amount_cents' => 3_000]],
        ]), PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        expect(array_keys($input->addOns))->toBe([0])
            ->and($input->addOns[0]->name)->toBe('Hot towel');
    });
});

describe('the rules it states', function () {
    it('refuses an appointment identifier that is no uuid', function (string $appointmentId) {
        expect(fn () => PaymentFixtures::createInput(appointmentId: $appointmentId)->validate())
            ->toThrow(PaymentAppointmentNotFound::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a sequential int' => '1',
        'a word' => 'not-a-uuid',
    ]);

    it('refuses a payment method identifier that is no uuid', function (string $paymentMethodId) {
        expect(fn () => PaymentFixtures::createInput(paymentMethodId: $paymentMethodId)->validate())
            ->toThrow(PaymentMethodNotFound::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a sequential int' => '3',
        'a word' => 'cash',
    ]);

    it('accepts add-ons right up to the cap', function () {
        expect(fn () => PaymentFixtures::createInput(
            addOns: PaymentFixtures::addOns(CreateAppointmentPaymentInput::MAXIMUM_ADD_ONS),
        )->validate())->not->toThrow(Throwable::class);
    });

    it('refuses one add-on past the cap', function () {
        expect(fn () => PaymentFixtures::createInput(
            addOns: PaymentFixtures::addOns(CreateAppointmentPaymentInput::MAXIMUM_ADD_ONS + 1),
        )->validate())->toThrow(TooManyPaymentItems::class);
    });

    it('refuses the whole payment when a single add-on names nothing', function () {
        expect(fn () => PaymentFixtures::createInput(addOns: [
            new PaymentAddOnInput('Beard trim', 12_000),
            new PaymentAddOnInput('   ', 3_000),
        ])->validate())->toThrow(InvalidPaymentItemName::class);
    });

    it('refuses an add-on named past the maximum length', function () {
        expect(fn () => PaymentFixtures::createInput(addOns: [
            new PaymentAddOnInput(str_repeat('a', PaymentItemName::MAXIMUM_LENGTH + 1), 3_000),
        ])->validate())->toThrow(InvalidPaymentItemName::class);
    });

    it('refuses an add-on priced outside the bounds money can hold', function (int $amountCents) {
        expect(fn () => PaymentFixtures::createInput(addOns: [
            new PaymentAddOnInput('Beard trim', $amountCents),
        ])->validate())->toThrow(InvalidPaymentItemAmount::class);
    })->with([
        'a negative amount' => -1,
        'one cent past the maximum' => Money::MAXIMUM_CENTS + 1,
    ]);

    it('refuses a discount its own rules refuse', function (string $type, int $value) {
        expect(fn () => PaymentFixtures::createInput(discount: new DiscountInput($type, $value))->validate())
            ->toThrow(InvalidPaymentDiscount::class);
    })->with([
        'an invented type' => ['buy_one_get_one', 0],
        'a percentage past the whole' => ['percentage', Discount::MAXIMUM_BASIS_POINTS + 1],
        'a negative fixed amount' => ['fixed', -1],
    ]);

    it('accepts no discount at all', function () {
        $input = PaymentFixtures::createInput(discount: null);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class)
            ->and($input->toDiscount()->type)->toBe(DiscountType::None);
    });

    it('refuses a first transaction of nothing', function (int $amountCents) {
        expect(fn () => PaymentFixtures::createInput(amountCents: $amountCents)->validate())
            ->toThrow(InvalidTransactionAmount::class);
    })->with([
        'no money at all' => 0,
        'a negative amount' => -1,
    ]);

    it('refuses a first transaction past the bounds money can hold', function () {
        expect(fn () => PaymentFixtures::createInput(amountCents: Money::MAXIMUM_CENTS + 1)->validate())
            ->toThrow(InvalidTransactionAmount::class);
    });

    it('accepts a first transaction of a single cent', function () {
        expect(fn () => PaymentFixtures::createInput(amountCents: 1)->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses an actor identifier that is no uuid', function (string $actorAccountId) {
        expect(fn () => PaymentFixtures::createInput(actorAccountId: $actorAccountId)->validate())
            ->toThrow(InvalidPaymentActor::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a sequential int' => '1',
        'a word' => 'the-owner',
    ]);

    it('rules on the appointment before the payment method', function () {
        expect(fn () => PaymentFixtures::createInput(appointmentId: 'nope', paymentMethodId: 'nope')->validate())
            ->toThrow(PaymentAppointmentNotFound::class);
    });

    it('rules on the amount before the actor', function () {
        expect(fn () => PaymentFixtures::createInput(amountCents: 0, actorAccountId: 'nope')->validate())
            ->toThrow(InvalidTransactionAmount::class);
    });

    it('rules on the payment method before the add-ons', function () {
        expect(fn () => PaymentFixtures::createInput(
            addOns: [new PaymentAddOnInput('', 1_000)],
            paymentMethodId: 'nope',
        )->validate())->toThrow(PaymentMethodNotFound::class);
    });
});

describe('turning itself into a discount', function () {
    it('hands back no discount when the payload named none', function () {
        expect(PaymentFixtures::createInput(discount: null)->toDiscount()->isNone())->toBeTrue();
    });

    it('hands back the discount the payload described', function () {
        $discount = PaymentFixtures::createInput(discount: new DiscountInput('fixed', 2_500))->toDiscount();

        expect($discount->type)->toBe(DiscountType::Fixed)
            ->and($discount->value)->toBe(2_500);
    });
});
