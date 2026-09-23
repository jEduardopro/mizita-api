<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Resources;

use App\Domains\PublicCatalog\ValueObjects\PublicState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PublicState $resource
 */
final class PublicStateResource extends JsonResource
{
    /**
     * @return array{id: string, code: string, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
        ];
    }
}
