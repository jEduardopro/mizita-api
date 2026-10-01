<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Resources;

use App\Domains\Businesses\Application\Dtos\AccountBusinessData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AccountBusinessData $resource
 */
final class AccountBusinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...BusinessResource::make($this->resource->business)->toArray($request),
            'role' => $this->resource->role->value,
            'is_current' => $this->resource->isCurrent,
        ];
    }
}
