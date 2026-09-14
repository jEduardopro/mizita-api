<?php

declare(strict_types=1);

namespace App\Domains\Industries\Infrastructure\Http\Resources;

use App\Domains\Industries\Application\Dtos\IndustryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IndustryData $resource
 */
final class IndustryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'position' => $this->resource->position,
        ];
    }
}
