<?php

declare(strict_types=1);

namespace App\Domains\Payments\Entities;

use App\Domains\Payments\Exceptions\CurrencyMismatch;
use App\Domains\Payments\Exceptions\DiscountExceedsSubtotal;
use App\Domains\Payments\Exceptions\InvalidTransactionAmount;
use App\Domains\Payments\Exceptions\PaymentAlreadySettled;
use App\Domains\Payments\Exceptions\PaymentAlreadyStarted;
use App\Domains\Payments\Exceptions\PaymentOverpaid;
use App\Domains\Payments\Exceptions\PaymentTransactionNotFound;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentItemName;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;

final class Payment
{
    /**
     * @param  list<PaymentItem>  $items
     * @param  list<PaymentTransaction>  $transactions
     */
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $appointmentId,
        private CurrencyCode $currency,
        private array $items,
        private Discount $discount,
        private array $transactions,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function open(
        string $id,
        string $businessId,
        string $appointmentId,
        CurrencyCode $currency,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            appointmentId: $appointmentId,
            currency: $currency,
            items: [],
            discount: Discount::none(),
            transactions: [],
            createdAt: $now,
        );
    }

    /**
     * @param  list<PaymentItem>  $items
     * @param  list<PaymentTransaction>  $transactions
     */
    public static function restore(
        string $id,
        string $businessId,
        string $appointmentId,
        CurrencyCode $currency,
        array $items,
        Discount $discount,
        array $transactions,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            appointmentId: $appointmentId,
            currency: $currency,
            items: $items,
            discount: $discount,
            transactions: $transactions,
            createdAt: $createdAt,
        );
    }

    /**
     * @throws CurrencyMismatch
     * @throws PaymentAlreadyStarted
     */
    public function addItem(string $itemId, PaymentItemName $name, Money $amount): void
    {
        $this->assertNotStarted();
        $this->assertOwnCurrency($amount);

        $this->items[] = PaymentItem::create($itemId, $name, $amount, count($this->items));
    }

    /**
     * @throws DiscountExceedsSubtotal
     * @throws PaymentAlreadyStarted
     */
    public function applyDiscount(Discount $discount): void
    {
        $this->assertNotStarted();

        $discount->amountOf($this->subtotal());

        $this->discount = $discount;
    }

    /**
     * @throws CurrencyMismatch
     * @throws InvalidTransactionAmount
     * @throws PaymentAlreadySettled
     * @throws PaymentOverpaid
     */
    public function recordTransaction(
        string $transactionId,
        string $paymentMethodId,
        Money $amount,
        DateTimeImmutable $now,
    ): PaymentTransaction {
        $this->assertOwnCurrency($amount);

        if ($amount->isZero()) {
            throw InvalidTransactionAmount::notPositive($amount->amount);
        }

        $balance = $this->balance();

        if ($balance->isZero()) {
            throw PaymentAlreadySettled::withId($this->id);
        }

        if ($amount->isGreaterThan($balance)) {
            throw PaymentOverpaid::byCents($amount->amount, $balance->amount);
        }

        $transaction = PaymentTransaction::record($transactionId, $paymentMethodId, $amount, $now);

        $this->transactions[] = $transaction;

        return $transaction;
    }

    /**
     * @throws PaymentTransactionNotFound
     */
    public function voidTransaction(string $transactionId, string $voidedByAccountId, DateTimeImmutable $now): void
    {
        $this->transactionWithId($transactionId)->void($voidedByAccountId, $now);
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function subtotal(): Money
    {
        $subtotal = Money::zero($this->currency);

        foreach ($this->items as $item) {
            $subtotal = $subtotal->plus($item->amount);
        }

        return $subtotal;
    }

    public function discount(): Discount
    {
        return $this->discount;
    }

    public function discountAmount(): Money
    {
        return $this->discount->amountOf($this->subtotal());
    }

    public function total(): Money
    {
        return $this->subtotal()->minus($this->discountAmount());
    }

    public function paid(): Money
    {
        $paid = Money::zero($this->currency);

        foreach ($this->transactions as $transaction) {
            if ($transaction->isVoided()) {
                continue;
            }

            $paid = $paid->plus($transaction->amount);
        }

        return $paid;
    }

    public function balance(): Money
    {
        return $this->total()->minus($this->paid());
    }

    public function status(): PaymentStatus
    {
        if ($this->balance()->isZero()) {
            return PaymentStatus::Paid;
        }

        if ($this->paid()->isZero()) {
            return PaymentStatus::Pending;
        }

        return PaymentStatus::PartiallyPaid;
    }

    public function isSettled(): bool
    {
        return $this->balance()->isZero();
    }

    /**
     * @return list<PaymentItem>
     */
    public function items(): array
    {
        return array_values($this->items);
    }

    /**
     * @return list<PaymentTransaction>
     */
    public function transactions(): array
    {
        return array_values($this->transactions);
    }

    /**
     * @throws PaymentAlreadyStarted
     */
    private function assertNotStarted(): void
    {
        foreach ($this->transactions as $transaction) {
            if (! $transaction->isVoided()) {
                throw PaymentAlreadyStarted::withId($this->id);
            }
        }
    }

    /**
     * @throws CurrencyMismatch
     */
    private function assertOwnCurrency(Money $amount): void
    {
        if (! $this->currency->equals($amount->currency)) {
            throw CurrencyMismatch::between($this->currency->value, $amount->currency->value);
        }
    }

    /**
     * @throws PaymentTransactionNotFound
     */
    private function transactionWithId(string $transactionId): PaymentTransaction
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->id === $transactionId) {
                return $transaction;
            }
        }

        throw PaymentTransactionNotFound::withId($transactionId);
    }
}
