<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Http\Resources;

use App\Domains\Customers\Application\Dtos\CustomerData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CustomerData $resource
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
        ];
    }
}
