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
use App\Domains\Payments\ValueObjects\Money;
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
            discount: Discount::restore($model->discount_type, $model->discount_value),
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
            'subtotal_cents' => $payment->subtotal()->amount,
            'discount_type' => $payment->discount()->type,
            'discount_value' => $payment->discount()->value,
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
        ?int $voidedByAccountKey,
    ): array {
        return [
            'uuid' => $transaction->id,
            'payment_id' => $paymentKey,
            'payment_method_id' => $paymentMethodKey,
            'amount_cents' => $transaction->amount->amount,
            'processed_at' => $transaction->processedAt->format(DATE_ATOM),
            'voided_at' => $transaction->voidedAt()?->format(DATE_ATOM),
            'voided_by_account_id' => $voidedByAccountKey,
        ];
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
                paymentMethodId: $transaction->paymentMethod->uuid,
                amount: Money::fromCents($transaction->amount_cents, $currency),
                processedAt: DateTimeImmutable::createFromInterface($transaction->processed_at),
                voidedAt: $transaction->voided_at,
                voidedByAccountId: $transaction->voidedByAccount?->uuid,
            ))
            ->values()
            ->all();
    }
}
