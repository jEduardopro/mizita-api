<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Resources;

use App\Domains\Services\Application\Dtos\ActiveServiceQuotaData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ActiveServiceQuotaData $resource
 */
final class ActiveServiceQuotaResource extends JsonResource
{
    /**
     * @return array{active_count: int, active_limit: int|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'active_count' => $this->resource->activeCount,
            'active_limit' => $this->resource->activeLimit,
        ];
    }
}
