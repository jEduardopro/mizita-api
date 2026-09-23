<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Resources;

use App\Domains\Payments\Application\Dtos\PaymentData;
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
            'discount_amount_cents' => $this->resource->discountAmountCents,
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
     * @return array{id: string, type: string, subtotal_pre_discount_cents: int, discount: array{type: string, value: int}, subtotal_discount_cents: int, subtotal_cents: int, total_cents: int, payment_method: array{id: string, code: string, label: string}, processed_at: string}
     */
    private static function describeTransaction(PaymentTransactionData $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'subtotal_pre_discount_cents' => $transaction->subtotalPreDiscountCents,
            'discount' => [
                'type' => $transaction->discountType->value,
                'value' => $transaction->discountValue,
            ],
            'subtotal_discount_cents' => $transaction->subtotalDiscountCents,
            'subtotal_cents' => $transaction->subtotalCents,
            'total_cents' => $transaction->totalCents,
            'payment_method' => [
                'id' => $transaction->paymentMethodId,
                'code' => $transaction->paymentMethodCode,
                'label' => PaymentMethodLabel::for($transaction->paymentMethodCode),
            ],
            'processed_at' => $transaction->processedAt->format(DATE_ATOM),
        ];
    }
}
