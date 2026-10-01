<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Resources;

use App\Domains\Payments\Application\Dtos\SaleData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SaleData $resource
 */
final class SaleResource extends JsonResource
{
    /**
     * @return array{id: string, created_at: string, customer: array{id: string, name: string}, status: string, total_cents: int, currency_code: string, reference_code: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
            'customer' => [
                'id' => $this->resource->customerId,
                'name' => $this->resource->customerName,
            ],
            'status' => $this->resource->status->value,
            'total_cents' => $this->resource->totalCents,
            'currency_code' => $this->resource->currencyCode,
            'reference_code' => $this->resource->referenceCode,
        ];
    }
}
