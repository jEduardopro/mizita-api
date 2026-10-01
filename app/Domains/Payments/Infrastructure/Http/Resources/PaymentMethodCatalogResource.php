<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Resources;

use App\Domains\Payments\Application\Dtos\BusinessPaymentMethodData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BusinessPaymentMethodData $resource
 */
final class PaymentMethodCatalogResource extends JsonResource
{
    /**
     * @return array{id: string, code: string, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => PaymentMethodLabel::for($this->resource->code),
        ];
    }
}
