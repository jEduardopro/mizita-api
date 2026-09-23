<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Mappers;

use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Entities\PaymentItem;
use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentItemModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentTransactionModel;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentBreakdown;
use App\Domains\Payments\ValueObjects\PaymentItemName;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;

final class PaymentMapper
{
    public function toEntity(PaymentModel $model, string $businessId): Payment
    {
        $currency = CurrencyCode::restore($model->currency_code);

        return Payment::restore(
            id: $model->uuid,
            businessId: $businessId,
            appointmentId: $model->appointment->uuid,
            currency: $currency,
            items: $this->itemsOf($model, $currency),
            discount: self::discountOf($model),
            transactions: $this->transactionsOf($model, $currency),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Payment $payment, int $businessKey, int $appointmentKey): array
    {
        return [
            'uuid' => $payment->id,
            'business_id' => $businessKey,
            'appointment_id' => $appointmentKey,
            'currency_code' => $payment->currency()->value,
            'discount_amount_cents' => $payment->discountAmount()->amount,
            'total_cents' => $payment->total()->amount,
            'paid_cents' => $payment->paid()->amount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemToAttributes(PaymentItem $item, int $paymentKey): array
    {
        return [
            'uuid' => $item->id,
            'payment_id' => $paymentKey,
            'name' => $item->name->value,
            'amount_cents' => $item->amount->amount,
            'position' => $item->position,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionToAttributes(
        PaymentTransaction $transaction,
        int $paymentKey,
        int $paymentMethodKey,
        ?int $accountKey,
    ): array {
        $breakdown = $transaction->breakdown;

        return [
            'uuid' => $transaction->id,
            'payment_id' => $paymentKey,
            'payment_method_id' => $paymentMethodKey,
            'account_id' => $accountKey,
            'type' => $transaction->type,
            'subtotal_pre_discount_cents' => $breakdown->subtotalPreDiscount->amount,
            'discount_type' => $breakdown->discountType(),
            'discount_value' => $breakdown->discountValue(),
            'subtotal_discount_cents' => $breakdown->discountAmount->amount,
            'subtotal_cents' => $breakdown->subtotal->amount,
            'total_cents' => $transaction->total->amount,
            'processed_at' => $transaction->processedAt->format(DATE_ATOM),
        ];
    }

    private static function discountOf(PaymentModel $model): Discount
    {
        if ($model->discount_amount_cents <= 0) {
            return Discount::none();
        }

        return Discount::restore(DiscountType::Fixed, $model->discount_amount_cents);
    }

    /**
     * @return list<PaymentItem>
     */
    private function itemsOf(PaymentModel $model, CurrencyCode $currency): array
    {
        return $model->items
            ->map(static fn (PaymentItemModel $item): PaymentItem => PaymentItem::restore(
                id: $item->uuid,
                name: PaymentItemName::restore($item->name),
                amount: Money::fromCents($item->amount_cents, $currency),
                position: $item->position,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<PaymentTransaction>
     */
    private function transactionsOf(PaymentModel $model, CurrencyCode $currency): array
    {
        return $model->transactions
            ->map(static fn (PaymentTransactionModel $transaction): PaymentTransaction => PaymentTransaction::restore(
                id: $transaction->uuid,
                type: $transaction->type,
                paymentMethodId: $transaction->paymentMethod->uuid,
                accountId: $transaction->account?->uuid,
                breakdown: self::breakdownOf($transaction, $currency),
                total: Money::fromCents($transaction->total_cents, $currency),
                processedAt: DateTimeImmutable::createFromInterface($transaction->processed_at),
            ))
            ->values()
            ->all();
    }

    private static function breakdownOf(PaymentTransactionModel $transaction, CurrencyCode $currency): PaymentBreakdown
    {
        return PaymentBreakdown::restore(
            subtotalPreDiscount: Money::fromCents($transaction->subtotal_pre_discount_cents, $currency),
            discount: Discount::restore($transaction->discount_type, $transaction->discount_value),
            discountAmount: Money::fromCents($transaction->subtotal_discount_cents, $currency),
            subtotal: Money::fromCents($transaction->subtotal_cents, $currency),
        );
    }
}
