<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Application\UseCases\RecordPaymentTransaction;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Payments\FakePaymentMethodCatalog;
use Tests\Support\Payments\FakePaymentRepository;
use Tests\Support\Payments\PaymentFixtures;
use Tests\Support\Payments\PaymentJournal;

beforeEach(function () {
    $this->journal = new PaymentJournal;
    $this->transactions = new FakeTransactionManager;

    $this->payments = (new FakePaymentRepository($this->journal, $this->transactions))
        ->store(PaymentFixtures::payment(
            transactions: [PaymentFixtures::transaction(amountCents: 20_000)],
        ));

    $this->cash = PaymentFixtures::paymentMethod(PaymentFixtures::CASH_METHOD_ID, 'cash', 1);
    $this->card = PaymentFixtures::paymentMethod(PaymentFixtures::CARD_METHOD_ID, 'card', 2);
    $this->transfer = PaymentFixtures::paymentMethod(PaymentFixtures::TRANSFER_METHOD_ID, 'transfer', 3);

    $this->paymentMethods = (new FakePaymentMethodCatalog($this->journal))
        ->enableFor(FakeBusinessContext::BUSINESS_ID, $this->cash, $this->card)
        ->disableFor(PaymentFixtures::OTHER_BUSINESS_ID, $this->cash)
        ->enableFor(PaymentFixtures::OTHER_BUSINESS_ID, $this->transfer);

    $this->build = fn (?FakeBusinessContext $business = null): RecordPaymentTransaction => new RecordPaymentTransaction(
        $this->payments,
        $this->paymentMethods,
        new PaymentPresenter($this->paymentMethods),
        new FixedIdGenerator(PaymentFixtures::GENERATED_TRANSACTION_ID),
        new FakeClock(PaymentFixtures::instant('2026-03-11T15:00:00+00:00')),
        $this->transactions,
        $business ?? new FakeBusinessContext,
    );

    $this->record = fn (...$overrides) => ($this->build)()
        ->handle(PaymentFixtures::recordInput(...$overrides));
});

describe('taking a further payment', function () {
    it('answers with the payment the new transaction settled', function () {
        $data = ($this->record)(amountCents: 30_000)->value();

        expect($data)->toBeInstanceOf(PaymentData::class)
            ->and($data->id)->toBe(PaymentFixtures::PAYMENT_ID)
            ->and($data->totalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->paidCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->balanceCents)->toBe(0)
            ->and($data->status)->toBe(PaymentStatus::Paid)
            ->and($data->transactions)->toHaveCount(2);
    });

    it('carries the new transaction with the identity and the clock it was handed', function () {
        $transaction = ($this->record)(
            paymentMethodId: PaymentFixtures::CARD_METHOD_ID,
            amountCents: 10_000,
        )->value()->transactions[1];

        expect($transaction->id)->toBe(PaymentFixtures::GENERATED_TRANSACTION_ID)
            ->and($transaction->paymentMethodId)->toBe(PaymentFixtures::CARD_METHOD_ID)
            ->and($transaction->paymentMethodCode)->toBe('card')
            ->and($transaction->amountCents)->toBe(10_000)
            ->and($transaction->status)->toBe(PaymentTransactionStatus::Completed)
            ->and($transaction->processedAt)->toEqual(PaymentFixtures::instant('2026-03-11T15:00:00+00:00'))
            ->and($transaction->voidedAt)->toBeNull();
    });

    it('leaves the payment part paid when the transaction does not cover the balance', function () {
        $data = ($this->record)(amountCents: 10_000)->value();

        expect($data->paidCents)->toBe(30_000)
            ->and($data->balanceCents)->toBe(20_000)
            ->and($data->status)->toBe(PaymentStatus::PartiallyPaid);
    });

    it('records the transaction in the currency the payment was opened in', function () {
        expect(($this->record)(amountCents: 10_000)->value()->currencyCode)
            ->toBe(PaymentFixtures::CURRENCY);
    });

    it('saves the payment it mutated', function () {
        ($this->record)(amountCents: 10_000);

        expect($this->payments->saved)->toHaveCount(1)
            ->and($this->payments->saved[0]->transactions())->toHaveCount(2);
    });
});

describe('the method the business has to have enabled', function () {
    it('resolves the method before it opens a transaction or touches the row', function () {
        ($this->record)(amountCents: 10_000);

        expect($this->journal->entries)->toBe([
            'paymentMethods.findEnabledFor',
            'payments.lockForBusiness',
            'payments.save',
            'paymentMethods.describeMany',
        ]);
    });

    it('refuses a method the business has not enabled', function () {
        $response = ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(PaymentFixtures::recordInput(amountCents: 10_000));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_method_not_enabled')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->payments->saved)->toBe([])
            ->and($this->payments->locks)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });

    it('refuses a method no catalogue carries', function () {
        $response = ($this->record)(paymentMethodId: PaymentFixtures::UNKNOWN_ID, amountCents: 10_000);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_method_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });

    it('resolves the method against the business in context', function () {
        ($this->record)(amountCents: 10_000);

        expect($this->paymentMethods->reads)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'paymentMethodId' => PaymentFixtures::CASH_METHOD_ID,
        ]]);
    });
});

describe('the row it has to hold still', function () {
    it('locks the payment before it mutates it', function () {
        ($this->record)(amountCents: 10_000);

        expect($this->payments->locks)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'paymentId' => PaymentFixtures::PAYMENT_ID,
        ]]);
    });

    it('opens exactly one transaction and saves inside it', function () {
        ($this->record)(amountCents: 10_000);

        expect($this->transactions->runs())->toBe(1)
            ->and($this->payments->savedInsideTransaction)->toBe([true]);
    });

    it('shows nothing of a payment filed under another business', function () {
        $response = ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(PaymentFixtures::recordInput(
                paymentMethodId: PaymentFixtures::TRANSFER_METHOD_ID,
                amountCents: 10_000,
            ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_not_found')
            ->and($this->payments->locks)->toBe([[
                'businessId' => PaymentFixtures::OTHER_BUSINESS_ID,
                'paymentId' => PaymentFixtures::PAYMENT_ID,
            ]])
            ->and($this->payments->saved)->toBe([]);
    });
});

describe('refusing the transaction', function () {
    it('refuses what the input itself refuses, before it asks any neighbour', function (array $overrides, string $code) {
        $response = ($this->record)(...$overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->payments->saved)->toBe([]);
    })->with([
        'a payment id that is no uuid' => [['paymentId' => 'not-a-uuid'], 'payment_not_found'],
        'a payment method id that is no uuid' => [['paymentMethodId' => ''], 'payment_method_not_found'],
        'an amount past what money can hold' => [['amountCents' => 10_000_000_000], 'invalid_transaction_amount'],
    ]);

    it('refuses a payment nobody opened here', function () {
        $response = ($this->record)(paymentId: PaymentFixtures::UNKNOWN_ID, amountCents: 10_000);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a transaction larger than what is still owed', function () {
        $response = ($this->record)(amountCents: 30_001);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_overpaid')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a transaction against a payment that owes nothing', function () {
        $this->payments->store(PaymentFixtures::payment(
            transactions: [PaymentFixtures::transaction(amountCents: PaymentFixtures::SERVICE_PRICE_CENTS)],
        ));

        $response = ($this->record)(amountCents: 1);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_already_settled')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->payments->saved)->toBe([]);
    });
});
