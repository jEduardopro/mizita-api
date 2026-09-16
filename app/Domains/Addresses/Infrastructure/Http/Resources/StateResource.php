<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Http\Resources;

use App\Domains\Addresses\Application\Dtos\StateData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StateData $resource
 */
final class StateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'country_code' => $this->resource->countryCode,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'position' => $this->resource->position,
        ];
    }
}
