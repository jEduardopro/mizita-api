<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Application\UseCases\VoidPaymentTransaction;
use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\Payments\FakePaymentMethodCatalog;
use Tests\Support\Payments\FakePaymentRepository;
use Tests\Support\Payments\PaymentFixtures;
use Tests\Support\Payments\PaymentJournal;

beforeEach(function () {
    $this->voidedAt = '2026-03-11T15:00:00+00:00';
    $this->journal = new PaymentJournal;
    $this->transactions = new FakeTransactionManager;

    $this->payments = (new FakePaymentRepository($this->journal, $this->transactions))
        ->store(PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(id: PaymentFixtures::TRANSACTION_ID, amountCents: 20_000),
            PaymentFixtures::transaction(
                id: PaymentFixtures::SECOND_TRANSACTION_ID,
                paymentMethodId: PaymentFixtures::CARD_METHOD_ID,
                amountCents: 30_000,
            ),
        ]));

    $this->paymentMethods = (new FakePaymentMethodCatalog($this->journal))->register(
        PaymentFixtures::paymentMethod(PaymentFixtures::CASH_METHOD_ID, 'cash', 1),
        PaymentFixtures::paymentMethod(PaymentFixtures::CARD_METHOD_ID, 'card', 2),
    );

    $this->build = fn (?FakeBusinessContext $business = null): VoidPaymentTransaction => new VoidPaymentTransaction(
        $this->payments,
        new PaymentPresenter($this->paymentMethods),
        new FakeClock(PaymentFixtures::instant($this->voidedAt)),
        $this->transactions,
        $business ?? new FakeBusinessContext,
    );

    $this->void = fn (...$overrides) => ($this->build)()
        ->handle(PaymentFixtures::voidInput(...$overrides));
});

describe('voiding a transaction', function () {
    it('answers with the payment the void left behind', function () {
        $data = ($this->void)()->value();

        expect($data)->toBeInstanceOf(PaymentData::class)
            ->and($data->id)->toBe(PaymentFixtures::PAYMENT_ID)
            ->and($data->totalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->paidCents)->toBe(30_000)
            ->and($data->balanceCents)->toBe(20_000)
            ->and($data->status)->toBe(PaymentStatus::PartiallyPaid)
            ->and($data->transactions)->toHaveCount(2);
    });

    it('marks the transaction voided at the moment the clock reads', function () {
        $transaction = ($this->void)()->value()->transactions[0];

        expect($transaction->id)->toBe(PaymentFixtures::TRANSACTION_ID)
            ->and($transaction->status)->toBe(PaymentTransactionStatus::Voided)
            ->and($transaction->voidedAt)->toEqual(PaymentFixtures::instant($this->voidedAt));
    });

    it('leaves the other transactions alone', function () {
        $transaction = ($this->void)()->value()->transactions[1];

        expect($transaction->id)->toBe(PaymentFixtures::SECOND_TRANSACTION_ID)
            ->and($transaction->status)->toBe(PaymentTransactionStatus::Completed)
            ->and($transaction->voidedAt)->toBeNull();
    });

    it('leaves a payment whose every transaction was voided owing the whole total', function () {
        ($this->void)();
        $data = ($this->void)(transactionId: PaymentFixtures::SECOND_TRANSACTION_ID)->value();

        expect($data->paidCents)->toBe(0)
            ->and($data->balanceCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->status)->toBe(PaymentStatus::Pending);
    });

    it('saves the payment it mutated', function () {
        ($this->void)();

        expect($this->payments->saved)->toHaveCount(1)
            ->and($this->payments->saved[0]->id)->toBe(PaymentFixtures::PAYMENT_ID);
    });
});

describe('the account that answers for the void', function () {
    it('records the actor the caller was authenticated as', function () {
        ($this->void)();

        $voided = array_values(array_filter(
            $this->payments->saved[0]->transactions(),
            static fn (PaymentTransaction $transaction): bool => $transaction->isVoided(),
        ));

        expect($voided)->toHaveCount(1)
            ->and($voided[0]->id)->toBe(PaymentFixtures::TRANSACTION_ID)
            ->and($voided[0]->voidedByAccountId())->toBe(PaymentFixtures::ACTOR_ID);
    });

    it('records whichever actor the input names', function () {
        ($this->void)(actorAccountId: PaymentFixtures::OTHER_ACTOR_ID);

        expect($this->payments->saved[0]->transactions()[0]->voidedByAccountId())
            ->toBe(PaymentFixtures::OTHER_ACTOR_ID);
    });

    it('keeps the actor out of what the client reads back', function () {
        expect(get_object_vars(($this->void)()->value()->transactions[0]))
            ->not->toHaveKey('voidedByAccountId');
    });
});

describe('the row it has to hold still', function () {
    it('locks the payment before it mutates it', function () {
        ($this->void)();

        expect($this->payments->locks)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'paymentId' => PaymentFixtures::PAYMENT_ID,
        ]]);
    });

    it('opens exactly one transaction and saves inside it', function () {
        ($this->void)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->payments->savedInsideTransaction)->toBe([true]);
    });

    it('locks, saves, then describes what it committed', function () {
        ($this->void)();

        expect($this->journal->entries)->toBe([
            'payments.lockForBusiness',
            'payments.save',
            'paymentMethods.describeMany',
        ]);
    });
});

describe('refusing to void', function () {
    it('refuses what the input itself refuses, before it asks any neighbour', function (array $overrides, string $code) {
        $response = ($this->void)(...$overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->payments->saved)->toBe([]);
    })->with([
        'a payment id that is no uuid' => [['paymentId' => 'not-a-uuid'], 'payment_not_found'],
        'a transaction id that is no uuid' => [['transactionId' => ''], 'payment_transaction_not_found'],
    ]);

    it('refuses a payment nobody opened here', function () {
        $response = ($this->void)(paymentId: PaymentFixtures::UNKNOWN_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a transaction the payment never carried', function () {
        $response = ($this->void)(transactionId: PaymentFixtures::THIRD_TRANSACTION_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_transaction_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a transaction somebody has already voided', function () {
        ($this->void)();

        $response = ($this->void)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_transaction_already_voided')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->payments->saved)->toHaveCount(1);
    });

    it('shows nothing of a payment filed under another business', function () {
        $response = ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(PaymentFixtures::voidInput());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_not_found')
            ->and($this->payments->locks)->toBe([[
                'businessId' => PaymentFixtures::OTHER_BUSINESS_ID,
                'paymentId' => PaymentFixtures::PAYMENT_ID,
            ]])
            ->and($this->payments->saved)->toBe([]);
    });
});
