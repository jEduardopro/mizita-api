<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Resources;

use App\Domains\Payments\Application\Dtos\BusinessPaymentMethodData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BusinessPaymentMethodData $resource
 */
final class BusinessPaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'label' => PaymentMethodLabel::for($this->resource->code),
            'enabled' => $this->resource->enabled,
            'position' => $this->resource->position,
            'requires_integration' => $this->resource->requiresIntegration,
        ];
    }
}
