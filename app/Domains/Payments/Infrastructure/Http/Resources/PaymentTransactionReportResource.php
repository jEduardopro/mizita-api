<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Resources;

use App\Domains\Payments\Application\Dtos\PaymentTransactionReportData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PaymentTransactionReportData $resource
 */
final class PaymentTransactionReportResource extends JsonResource
{
    /**
     * @return array{id: string, processed_at: string, customer: array{id: string, name: string}, amount_cents: int, currency_code: string, type: string, method: array{code: string, name: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'processed_at' => $this->resource->processedAt->format(DATE_ATOM),
            'customer' => [
                'id' => $this->resource->customerId,
                'name' => $this->resource->customerName,
            ],
            'amount_cents' => $this->resource->amountCents,
            'currency_code' => $this->resource->currencyCode,
            'type' => $this->resource->type->value,
            'method' => [
                'code' => $this->resource->paymentMethodCode,
                'name' => PaymentMethodLabel::for($this->resource->paymentMethodCode),
            ],
        ];
    }
}
