<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Resources;

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\PaymentDiscountData;
use App\Domains\Payments\Application\Dtos\PaymentItemData;
use App\Domains\Payments\Application\Dtos\PaymentTransactionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PaymentData $resource
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'appointment_id' => $this->resource->appointmentId,
            'currency_code' => $this->resource->currencyCode,
            'status' => $this->resource->status->value,
            'items' => array_map(self::describeItem(...), $this->resource->items),
            'subtotal_cents' => $this->resource->subtotalCents,
            'discount' => self::describeDiscount($this->resource->discount),
            'total_cents' => $this->resource->totalCents,
            'paid_cents' => $this->resource->paidCents,
            'balance_cents' => $this->resource->balanceCents,
            'transactions' => array_map(self::describeTransaction(...), $this->resource->transactions),
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
        ];
    }

    /**
     * @return array{id: string, name: string, amount_cents: int, position: int}
     */
    private static function describeItem(PaymentItemData $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'amount_cents' => $item->amountCents,
            'position' => $item->position,
        ];
    }

    /**
     * @return array{type: string, value: int, amount_cents: int}
     */
    private static function describeDiscount(PaymentDiscountData $discount): array
    {
        return [
            'type' => $discount->type->value,
            'value' => $discount->value,
            'amount_cents' => $discount->amountCents,
        ];
    }

    /**
     * @return array{id: string, status: string, amount_cents: int, payment_method: array{id: string, code: string, label: string}, processed_at: string, voided_at: string|null}
     */
    private static function describeTransaction(PaymentTransactionData $transaction): array
    {
        return [
            'id' => $transaction->id,
            'status' => $transaction->status->value,
            'amount_cents' => $transaction->amountCents,
            'payment_method' => [
                'id' => $transaction->paymentMethodId,
                'code' => $transaction->paymentMethodCode,
                'label' => PaymentMethodLabel::for($transaction->paymentMethodCode),
            ],
            'processed_at' => $transaction->processedAt->format(DATE_ATOM),
            'voided_at' => $transaction->voidedAt?->format(DATE_ATOM),
        ];
    }
}
