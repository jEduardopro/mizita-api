<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\PaymentItemData;
use App\Domains\Payments\Application\Dtos\PaymentTransactionData;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionStatus;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Payments\FakePaymentMethodCatalog;
use Tests\Support\Payments\PaymentFixtures;

beforeEach(function () {
    $this->cash = PaymentFixtures::paymentMethod(PaymentFixtures::CASH_METHOD_ID, 'cash', 1);
    $this->card = PaymentFixtures::paymentMethod(PaymentFixtures::CARD_METHOD_ID, 'card', 2, requiresIntegration: true);

    $this->paymentMethods = (new FakePaymentMethodCatalog)->register($this->cash, $this->card);

    $this->presenter = new PaymentPresenter($this->paymentMethods);

    $this->describe = fn ($payment): PaymentData => $this->presenter
        ->describe(FakeBusinessContext::BUSINESS_ID, $payment);
});

describe('describing the money on a payment', function () {
    it('carries every total the client reads, derived from the entity', function () {
        $payment = PaymentFixtures::payment(
            items: [
                PaymentFixtures::item(amountCents: 50_000, position: 0),
                PaymentFixtures::item(id: PaymentFixtures::UNKNOWN_ID, name: 'Beard trim', amountCents: 10_000, position: 1),
            ],
            discount: Discount::restore(DiscountType::Percentage, 1_000),
            transactions: [PaymentFixtures::transaction(amountCents: 20_000)],
        );

        $data = ($this->describe)($payment);

        expect($data)->toBeInstanceOf(PaymentData::class)
            ->and($data->id)->toBe(PaymentFixtures::PAYMENT_ID)
            ->and($data->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID)
            ->and($data->currencyCode)->toBe(PaymentFixtures::CURRENCY)
            ->and($data->subtotalCents)->toBe(60_000)
            ->and($data->discount->type)->toBe(DiscountType::Percentage)
            ->and($data->discount->value)->toBe(1_000)
            ->and($data->discount->amountCents)->toBe(6_000)
            ->and($data->totalCents)->toBe(54_000)
            ->and($data->paidCents)->toBe(20_000)
            ->and($data->balanceCents)->toBe(34_000)
            ->and($data->status)->toBe(PaymentStatus::PartiallyPaid)
            ->and($data->createdAt)->toEqual(PaymentFixtures::now());
    });

    it('carries the line items in the order the payment holds them', function () {
        $payment = PaymentFixtures::payment(items: [
            PaymentFixtures::item(name: PaymentFixtures::SERVICE_NAME, amountCents: 50_000, position: 0),
            PaymentFixtures::item(id: PaymentFixtures::UNKNOWN_ID, name: 'Beard trim', amountCents: 10_000, position: 1),
        ]);

        $data = ($this->describe)($payment);

        expect($data->items)->toHaveCount(2)
            ->and($data->items[0])->toBeInstanceOf(PaymentItemData::class)
            ->and($data->items[0]->name)->toBe(PaymentFixtures::SERVICE_NAME)
            ->and($data->items[0]->amountCents)->toBe(50_000)
            ->and($data->items[0]->position)->toBe(0)
            ->and($data->items[1]->name)->toBe('Beard trim')
            ->and($data->items[1]->position)->toBe(1);
    });

    it('leaves a voided transaction out of what has been paid', function () {
        $payment = PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(amountCents: 20_000),
            PaymentFixtures::transaction(
                id: PaymentFixtures::SECOND_TRANSACTION_ID,
                amountCents: 30_000,
                voidedAt: '2026-03-11T09:00:00+00:00',
                voidedByAccountId: PaymentFixtures::ACTOR_ID,
            ),
        ]);

        $data = ($this->describe)($payment);

        expect($data->paidCents)->toBe(20_000)
            ->and($data->balanceCents)->toBe(30_000)
            ->and($data->status)->toBe(PaymentStatus::PartiallyPaid);
    });
});

describe('describing the transactions on a payment', function () {
    it('carries every field of a completed transaction', function () {
        $payment = PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(
                paymentMethodId: PaymentFixtures::CARD_METHOD_ID,
                amountCents: 15_000,
                processedAt: '2026-03-10T10:30:00+00:00',
            ),
        ]);

        $transaction = ($this->describe)($payment)->transactions[0];

        expect($transaction)->toBeInstanceOf(PaymentTransactionData::class)
            ->and($transaction->id)->toBe(PaymentFixtures::TRANSACTION_ID)
            ->and($transaction->paymentMethodId)->toBe(PaymentFixtures::CARD_METHOD_ID)
            ->and($transaction->paymentMethodCode)->toBe('card')
            ->and($transaction->amountCents)->toBe(15_000)
            ->and($transaction->status)->toBe(PaymentTransactionStatus::Completed)
            ->and($transaction->processedAt)->toEqual(PaymentFixtures::instant('2026-03-10T10:30:00+00:00'))
            ->and($transaction->voidedAt)->toBeNull();
    });

    it('carries when a voided transaction was voided', function () {
        $payment = PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(
                amountCents: 15_000,
                voidedAt: '2026-03-11T09:00:00+00:00',
                voidedByAccountId: PaymentFixtures::ACTOR_ID,
            ),
        ]);

        $transaction = ($this->describe)($payment)->transactions[0];

        expect($transaction->status)->toBe(PaymentTransactionStatus::Voided)
            ->and($transaction->voidedAt)->toEqual(PaymentFixtures::instant('2026-03-11T09:00:00+00:00'));
    });

    it('keeps the transactions in the order the payment holds them', function () {
        $payment = PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(id: PaymentFixtures::THIRD_TRANSACTION_ID, amountCents: 1_000),
            PaymentFixtures::transaction(id: PaymentFixtures::TRANSACTION_ID, amountCents: 2_000),
            PaymentFixtures::transaction(id: PaymentFixtures::SECOND_TRANSACTION_ID, amountCents: 3_000),
        ]);

        expect(array_map(
            static fn (PaymentTransactionData $data): string => $data->id,
            ($this->describe)($payment)->transactions,
        ))->toBe([
            PaymentFixtures::THIRD_TRANSACTION_ID,
            PaymentFixtures::TRANSACTION_ID,
            PaymentFixtures::SECOND_TRANSACTION_ID,
        ]);
    });

    it('hands back every id as the uuid the client already holds', function () {
        $payment = PaymentFixtures::payment(transactions: [PaymentFixtures::transaction()]);

        $data = ($this->describe)($payment);

        expect($data->id)->toBe(PaymentFixtures::PAYMENT_ID)
            ->and($data->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID)
            ->and($data->items[0]->id)->toBe(PaymentFixtures::ITEM_ID)
            ->and($data->transactions[0]->id)->toBe(PaymentFixtures::TRANSACTION_ID)
            ->and($data->transactions[0]->paymentMethodId)->toBe(PaymentFixtures::CASH_METHOD_ID);
    });
});

describe('resolving the payment methods behind the transactions', function () {
    it('asks the catalogue once for the whole payment, never once per transaction', function () {
        $payment = PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(id: PaymentFixtures::TRANSACTION_ID, amountCents: 1_000),
            PaymentFixtures::transaction(id: PaymentFixtures::SECOND_TRANSACTION_ID, amountCents: 2_000),
            PaymentFixtures::transaction(
                id: PaymentFixtures::THIRD_TRANSACTION_ID,
                paymentMethodId: PaymentFixtures::CARD_METHOD_ID,
                amountCents: 3_000,
            ),
        ]);

        ($this->describe)($payment);

        expect($this->paymentMethods->batchReads)->toHaveCount(1)
            ->and($this->paymentMethods->reads)->toBe([])
            ->and($this->paymentMethods->journal->entries)->toBe(['paymentMethods.describeMany']);
    });

    it('asks for one id when several transactions share a method', function () {
        $payment = PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(id: PaymentFixtures::TRANSACTION_ID, amountCents: 1_000),
            PaymentFixtures::transaction(id: PaymentFixtures::SECOND_TRANSACTION_ID, amountCents: 2_000),
            PaymentFixtures::transaction(
                id: PaymentFixtures::THIRD_TRANSACTION_ID,
                paymentMethodId: PaymentFixtures::CARD_METHOD_ID,
                amountCents: 3_000,
            ),
        ]);

        ($this->describe)($payment);

        expect($this->paymentMethods->batchReads[0])
            ->toBe([PaymentFixtures::CASH_METHOD_ID, PaymentFixtures::CARD_METHOD_ID]);
    });

    it('still describes each transaction with its own method after asking once', function () {
        $payment = PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(id: PaymentFixtures::TRANSACTION_ID, amountCents: 1_000),
            PaymentFixtures::transaction(
                id: PaymentFixtures::SECOND_TRANSACTION_ID,
                paymentMethodId: PaymentFixtures::CARD_METHOD_ID,
                amountCents: 2_000,
            ),
            PaymentFixtures::transaction(id: PaymentFixtures::THIRD_TRANSACTION_ID, amountCents: 3_000),
        ]);

        expect(array_map(
            static fn (PaymentTransactionData $data): string => $data->paymentMethodCode,
            ($this->describe)($payment)->transactions,
        ))->toBe(['cash', 'card', 'cash']);
    });

    it('asks the catalogue nothing for a payment nobody has paid yet', function () {
        $data = ($this->describe)(PaymentFixtures::payment());

        expect($data->transactions)->toBe([])
            ->and($data->paidCents)->toBe(0)
            ->and($data->status)->toBe(PaymentStatus::Pending)
            ->and($this->paymentMethods->journal->entries)->toBe([]);
    });

    it('refuses to describe a transaction whose method the catalogue has lost', function () {
        $payment = PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(paymentMethodId: PaymentFixtures::TRANSFER_METHOD_ID),
        ]);

        expect(fn () => ($this->describe)($payment))->toThrow(PaymentMethodNotFound::class);
    });
});
