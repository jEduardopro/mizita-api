<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\CreateAppointmentPaymentInput;
use App\Domains\Payments\Application\Dtos\DiscountInput;
use App\Domains\Payments\Application\Dtos\PaymentAddOnInput;
use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\PaymentItemData;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Application\UseCases\CreateAppointmentPayment;
use App\Domains\Payments\Exceptions\AppointmentAlreadyHasPayment;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\Payments\FakeAppointmentDirectory;
use Tests\Support\Payments\FakeBusinessProfile;
use Tests\Support\Payments\FakeCalendarAccess;
use Tests\Support\Payments\FakePaymentMethodCatalog;
use Tests\Support\Payments\FakePaymentRepository;
use Tests\Support\Payments\FakeServiceCatalog;
use Tests\Support\Payments\PaymentFixtures;
use Tests\Support\Payments\PaymentJournal;

beforeEach(function () {
    $this->journal = new PaymentJournal;
    $this->transactions = new FakeTransactionManager;
    $this->payments = new FakePaymentRepository($this->journal, $this->transactions);

    $this->businesses = (new FakeBusinessProfile($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, PaymentFixtures::currency());

    $this->appointments = (new FakeAppointmentDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, PaymentFixtures::appointmentSnapshot())
        ->add(FakeBusinessContext::BUSINESS_ID, PaymentFixtures::appointmentSnapshot(
            id: PaymentFixtures::SECOND_APPOINTMENT_ID,
            serviceId: PaymentFixtures::UNKNOWN_ID,
        ))
        ->add(FakeBusinessContext::BUSINESS_ID, PaymentFixtures::appointmentSnapshot(
            id: PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID,
            staffMemberId: PaymentFixtures::OTHER_STAFF_MEMBER_ID,
        ))
        ->add(PaymentFixtures::OTHER_BUSINESS_ID, PaymentFixtures::appointmentSnapshot());
    $this->calendars = FakeCalendarAccess::everyone();

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(
            FakeBusinessContext::BUSINESS_ID,
            PaymentFixtures::SERVICE_ID,
            PaymentFixtures::SERVICE_NAME,
            PaymentFixtures::SERVICE_PRICE_CENTS,
        );

    $this->cash = PaymentFixtures::paymentMethod(PaymentFixtures::CASH_METHOD_ID, 'cash', 1);
    $this->card = PaymentFixtures::paymentMethod(PaymentFixtures::CARD_METHOD_ID, 'card', 2);

    $this->paymentMethods = (new FakePaymentMethodCatalog($this->journal))
        ->enableFor(FakeBusinessContext::BUSINESS_ID, $this->cash)
        ->disableFor(FakeBusinessContext::BUSINESS_ID, $this->card);

    $this->build = fn (int $addOns = 0, ?FakeBusinessContext $business = null): CreateAppointmentPayment => new CreateAppointmentPayment(
        $this->payments,
        $this->appointments,
        $this->calendars,
        $this->services,
        $this->businesses,
        $this->paymentMethods,
        new PaymentPresenter($this->paymentMethods),
        PaymentFixtures::idGenerator($addOns),
        new FakeClock(PaymentFixtures::now()),
        $this->transactions,
        $business ?? new FakeBusinessContext,
    );

    $this->create = fn (...$overrides) => ($this->build)(count($overrides['addOns'] ?? []))
        ->handle(PaymentFixtures::createInput(...$overrides));
});

describe('charging an appointment', function () {
    it('answers with every field the client reads', function () {
        $data = ($this->create)()->value();

        expect($data)->toBeInstanceOf(PaymentData::class)
            ->and($data->id)->toBe(PaymentFixtures::GENERATED_PAYMENT_ID)
            ->and($data->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID)
            ->and($data->currencyCode)->toBe(PaymentFixtures::CURRENCY)
            ->and($data->subtotalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->discountAmountCents)->toBe(0)
            ->and($data->totalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->paidCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->balanceCents)->toBe(0)
            ->and($data->status)->toBe(PaymentStatus::Paid)
            ->and($data->createdAt)->toEqual(PaymentFixtures::now());
    });

    it('saves the payment the identity and the clock handed it', function () {
        ($this->create)();

        expect($this->payments->saved)->toHaveCount(1)
            ->and($this->payments->saved[0]->id)->toBe(PaymentFixtures::GENERATED_PAYMENT_ID)
            ->and($this->payments->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->payments->saved[0]->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID)
            ->and($this->payments->saved[0]->createdAt)->toEqual(PaymentFixtures::now());
    });

    it('leaves a part-paid appointment with a balance still owing', function () {
        $data = ($this->create)(amountCents: 20_000)->value();

        expect($data->paidCents)->toBe(20_000)
            ->and($data->balanceCents)->toBe(30_000)
            ->and($data->status)->toBe(PaymentStatus::PartiallyPaid);
    });
});

describe('the service line nobody may price', function () {
    it('seeds the first line from the catalogue, not from anything the caller sent', function () {
        $data = ($this->create)()->value();

        expect($data->items)->toHaveCount(1)
            ->and($data->items[0])->toBeInstanceOf(PaymentItemData::class)
            ->and($data->items[0]->id)->toBe(PaymentFixtures::GENERATED_SERVICE_ITEM_ID)
            ->and($data->items[0]->name)->toBe(PaymentFixtures::SERVICE_NAME)
            ->and($data->items[0]->amountCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->items[0]->position)->toBe(0);
    });

    it('ignores a payload that tries to name and price the service itself', function () {
        $input = CreateAppointmentPaymentInput::fromRequest(PaymentFixtures::createPayload([
            'items' => [['name' => 'Free haircut', 'amount_cents' => 0]],
            'service_name' => 'Free haircut',
            'service_price_cents' => 1,
            'subtotal_cents' => 1,
            'total_cents' => 1,
            'amount_cents' => PaymentFixtures::SERVICE_PRICE_CENTS,
        ]), PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        $data = ($this->build)()->handle($input)->value();

        expect($data->items)->toHaveCount(1)
            ->and($data->items[0]->name)->toBe(PaymentFixtures::SERVICE_NAME)
            ->and($data->items[0]->amountCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->subtotalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->totalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS);
    });

    it('keeps the service line first even when an add-on impersonates it', function () {
        $data = ($this->create)(addOns: [new PaymentAddOnInput(PaymentFixtures::SERVICE_NAME, 1)])->value();

        expect($data->items[0]->id)->toBe(PaymentFixtures::GENERATED_SERVICE_ITEM_ID)
            ->and($data->items[0]->amountCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->items[0]->position)->toBe(0)
            ->and($data->items[1]->name)->toBe(PaymentFixtures::SERVICE_NAME)
            ->and($data->items[1]->amountCents)->toBe(1)
            ->and($data->items[1]->position)->toBe(1)
            ->and($data->subtotalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS + 1);
    });

    it('follows the catalogue when the catalogue reprices the service', function () {
        $this->services->add(
            FakeBusinessContext::BUSINESS_ID,
            PaymentFixtures::SERVICE_ID,
            'Premium Haircut',
            75_000,
        );

        $data = ($this->create)(amountCents: 75_000)->value();

        expect($data->items[0]->name)->toBe('Premium Haircut')
            ->and($data->items[0]->amountCents)->toBe(75_000)
            ->and($data->totalCents)->toBe(75_000);
    });

    it('reads the service the appointment names, never one the caller names', function () {
        ($this->create)();

        expect($this->services->reads)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'serviceId' => PaymentFixtures::SERVICE_ID,
            'currencyCode' => PaymentFixtures::CURRENCY,
        ]]);
    });

    it('prices the service in the currency the business trades in', function () {
        $this->businesses->add(FakeBusinessContext::BUSINESS_ID, PaymentFixtures::currency('USD'));

        expect(($this->create)()->value()->currencyCode)->toBe('USD')
            ->and($this->services->reads[0]['currencyCode'])->toBe('USD');
    });
});

describe('the add-ons the caller did send', function () {
    it('lands them in order after the service line', function () {
        $data = ($this->create)(
            addOns: [
                new PaymentAddOnInput('Beard trim', 12_000),
                new PaymentAddOnInput('Hot towel', 3_000),
            ],
            amountCents: 65_000,
        )->value();

        expect($data->items)->toHaveCount(3)
            ->and(array_map(static fn (PaymentItemData $item): string => $item->name, $data->items))
            ->toBe([PaymentFixtures::SERVICE_NAME, 'Beard trim', 'Hot towel'])
            ->and(array_map(static fn (PaymentItemData $item): int => $item->position, $data->items))
            ->toBe([0, 1, 2])
            ->and(array_map(static fn (PaymentItemData $item): string => $item->id, $data->items))
            ->toBe([
                PaymentFixtures::GENERATED_SERVICE_ITEM_ID,
                PaymentFixtures::GENERATED_FIRST_ADD_ON_ID,
                PaymentFixtures::GENERATED_SECOND_ADD_ON_ID,
            ]);
    });

    it('adds them to the subtotal', function () {
        $data = ($this->create)(
            addOns: [new PaymentAddOnInput('Beard trim', 12_000)],
            amountCents: 62_000,
        )->value();

        expect($data->subtotalCents)->toBe(62_000)
            ->and($data->totalCents)->toBe(62_000);
    });

    it('trims the name an add-on arrived with', function () {
        $data = ($this->create)(
            addOns: [new PaymentAddOnInput('  Beard trim  ', 12_000)],
            amountCents: 62_000,
        )->value();

        expect($data->items[1]->name)->toBe('Beard trim');
    });

    it('accepts an add-on that costs nothing', function () {
        $data = ($this->create)(addOns: [new PaymentAddOnInput('Complimentary wash', 0)])->value();

        expect($data->items[1]->amountCents)->toBe(0)
            ->and($data->subtotalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS);
    });
});

describe('the discount', function () {
    it('takes a percentage off the subtotal', function () {
        $data = ($this->create)(
            discount: new DiscountInput('percentage', 1_000),
            amountCents: 45_000,
        )->value();

        expect($data->subtotalCents)->toBe(50_000)
            ->and($data->discountAmountCents)->toBe(5_000)
            ->and($data->totalCents)->toBe(45_000);
    });

    it('takes a fixed amount off the subtotal', function () {
        $data = ($this->create)(
            discount: new DiscountInput('fixed', 7_500),
            amountCents: 42_500,
        )->value();

        expect($data->discountAmountCents)->toBe(7_500)
            ->and($data->totalCents)->toBe(42_500);
    });

    it('takes the percentage off the add-ons too', function () {
        $data = ($this->create)(
            addOns: [new PaymentAddOnInput('Beard trim', 10_000)],
            discount: new DiscountInput('percentage', 5_000),
            amountCents: 30_000,
        )->value();

        expect($data->subtotalCents)->toBe(60_000)
            ->and($data->discountAmountCents)->toBe(30_000)
            ->and($data->totalCents)->toBe(30_000);
    });

    it('takes nothing off when the payload named no discount', function () {
        $data = ($this->create)(discount: null)->value();

        expect($data->discountAmountCents)->toBe(0)
            ->and($data->totalCents)->toBe($data->subtotalCents);
    });

    it('carries no discount object of its own, only the amount it took off', function () {
        expect(get_object_vars(($this->create)()->value()))->not->toHaveKey('discount');
    });
});

describe('the first transaction', function () {
    it('records it in the same call, through the method the business enabled', function () {
        $data = ($this->create)()->value();

        expect($data->transactions)->toHaveCount(1)
            ->and($data->transactions[0]->id)->toBe(PaymentFixtures::GENERATED_TRANSACTION_ID)
            ->and($data->transactions[0]->paymentMethodId)->toBe(PaymentFixtures::CASH_METHOD_ID)
            ->and($data->transactions[0]->paymentMethodCode)->toBe('cash')
            ->and($data->transactions[0]->totalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->transactions[0]->type)->toBe(PaymentTransactionType::Approved)
            ->and($data->transactions[0]->processedAt)->toEqual(PaymentFixtures::now());
    });

    it('carries the breakdown the payment was priced with onto the first transaction', function () {
        $transaction = ($this->create)(
            discount: new DiscountInput('percentage', 1_000),
            amountCents: 45_000,
        )->value()->transactions[0];

        expect($transaction->subtotalPreDiscountCents)->toBe(50_000)
            ->and($transaction->discountType)->toBe(DiscountType::Percentage)
            ->and($transaction->discountValue)->toBe(1_000)
            ->and($transaction->subtotalDiscountCents)->toBe(5_000)
            ->and($transaction->subtotalCents)->toBe(45_000)
            ->and($transaction->totalCents)->toBe(45_000);
    });

    it('stamps the first transaction with the account the caller was authenticated as', function () {
        ($this->create)();

        expect($this->payments->saved[0]->transactions()[0]->accountId)
            ->toBe(PaymentFixtures::ACTOR_ID);
    });

    it('never hands the account that took the money back to the client', function () {
        expect(get_object_vars(($this->create)()->value()->transactions[0]))
            ->not->toHaveKey('accountId');
    });

    it('resolves the method against the business, not against the catalogue alone', function () {
        ($this->create)();

        expect($this->paymentMethods->reads)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'paymentMethodId' => PaymentFixtures::CASH_METHOD_ID,
        ]]);
    });
});

describe('the transaction it all runs in', function () {
    it('opens exactly one, and saves inside it', function () {
        ($this->create)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->payments->savedInsideTransaction)->toBe([true]);
    });

    it('reads its neighbours, records and saves, then describes what it committed', function () {
        ($this->create)();

        expect($this->journal->entries)->toBe([
            'businesses.currencyFor',
            'appointments.describe',
            'services.describe',
            'paymentMethods.findEnabledFor',
            'payments.save',
            'paymentMethods.describeMany',
        ]);
    });

    it('opens no transaction at all when the input itself is refused', function () {
        ($this->create)(appointmentId: 'not-a-uuid');

        expect($this->transactions->runs())->toBe(0)
            ->and($this->journal->entries)->toBe([]);
    });
});

describe('the business it belongs to', function () {
    it('scopes the payment to the business in context', function () {
        ($this->create)();

        expect($this->payments->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('asks every neighbour about the business in context, never about one the payload names', function () {
        $input = CreateAppointmentPaymentInput::fromRequest(PaymentFixtures::createPayload([
            'business_id' => PaymentFixtures::OTHER_BUSINESS_ID,
        ]), PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID);

        ($this->build)()->handle($input);

        expect($this->businesses->reads)->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and(array_column($this->appointments->reads, 'businessId'))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and(array_column($this->services->reads, 'businessId'))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and(array_column($this->paymentMethods->reads, 'businessId'))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('does not carry the business it belongs to into the data', function () {
        expect(get_object_vars(($this->create)()->value()))->not->toHaveKey('businessId');
    });

    it('refuses an appointment that belongs to another business', function () {
        $response = ($this->build)(0, new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(PaymentFixtures::createInput());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_business_not_found')
            ->and($this->payments->saved)->toBe([]);
    });
});

describe('refusing to charge', function () {
    it('refuses what the input itself refuses, before it asks any neighbour', function (array $overrides, string $code) {
        $response = ($this->create)(...$overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($this->journal->entries)->toBe([])
            ->and($this->payments->saved)->toBe([]);
    })->with([
        'an appointment id that is no uuid' => [['appointmentId' => 'not-a-uuid'], 'payment_appointment_not_found'],
        'a payment method id that is no uuid' => [['paymentMethodId' => ''], 'payment_method_not_found'],
        'an add-on nobody named' => [
            ['addOns' => [new PaymentAddOnInput('   ', 1_000)]],
            'invalid_payment_item_name',
        ],
        'a discount type nobody offers' => [
            ['discount' => new DiscountInput('buy_one_get_one', 0)],
            'invalid_payment_discount',
        ],
        'an amount past what money can hold' => [['amountCents' => 10_000_000_000], 'invalid_transaction_amount'],
        'an actor id that is no uuid' => [['actorAccountId' => 'the-owner'], 'invalid_payment_actor'],
    ]);

    it('refuses an appointment nobody booked here', function () {
        $response = ($this->create)(appointmentId: PaymentFixtures::UNKNOWN_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses an appointment whose service the catalogue has lost', function () {
        $response = ($this->create)(appointmentId: PaymentFixtures::SECOND_APPOINTMENT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_service_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a payment method the business has not enabled', function () {
        $response = ($this->create)(paymentMethodId: PaymentFixtures::CARD_METHOD_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_method_not_enabled')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a payment method no catalogue carries', function () {
        $response = ($this->create)(paymentMethodId: PaymentFixtures::UNKNOWN_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_method_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a first transaction larger than the total', function () {
        $response = ($this->create)(amountCents: PaymentFixtures::SERVICE_PRICE_CENTS + 1);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_overpaid')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a fixed discount larger than the subtotal', function () {
        $response = ($this->create)(discount: new DiscountInput('fixed', PaymentFixtures::SERVICE_PRICE_CENTS + 1));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('discount_exceeds_subtotal')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a service priced in a currency the business does not trade in', function () {
        $this->services->pricedIn(PaymentFixtures::currency(PaymentFixtures::OTHER_CURRENCY));

        $response = ($this->create)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('currency_mismatch')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses an appointment somebody has already charged', function () {
        $this->payments->failingOnSave(
            AppointmentAlreadyHasPayment::forAppointment(PaymentFixtures::APPOINTMENT_ID),
        );

        $response = ($this->create)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_already_has_payment')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->payments->saved)->toBe([]);
    });
});

describe('a caller who keeps only their own calendar', function () {
    beforeEach(function () {
        $this->calendars = FakeCalendarAccess::ownedBy(PaymentFixtures::STAFF_MEMBER_ID);
    });

    it('charges an appointment on their own calendar', function () {
        $data = ($this->create)()->value();

        expect($data->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID)
            ->and($this->payments->saved)->toHaveCount(1);
    });

    it('answers not found for an appointment on the calendar of another team member', function () {
        $response = ($this->create)(appointmentId: PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('opens no payment and prices nothing for an appointment it refused', function () {
        ($this->create)(appointmentId: PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID);

        expect($this->payments->saved)->toBe([])
            ->and($this->services->reads)->toBe([])
            ->and($this->journal->entries)->not->toContain('payments.save');
    });
});

describe('the calendar the caller is allowed to keep', function () {
    it('asks for the scope of the actor, in the business in context', function () {
        ($this->create)(actorAccountId: PaymentFixtures::OTHER_ACTOR_ID);

        expect($this->calendars->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => PaymentFixtures::OTHER_ACTOR_ID,
        ]]);
    });

    it('lets a caller who keeps every calendar charge an appointment of any team member', function () {
        $data = ($this->create)(appointmentId: PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID)->value();

        expect($data->appointmentId)->toBe(PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID);
    });

    it('refuses an actor that is no member of the business and opens no payment', function () {
        $this->calendars = FakeCalendarAccess::refusing();

        $response = ($this->create)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_account_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });
});
