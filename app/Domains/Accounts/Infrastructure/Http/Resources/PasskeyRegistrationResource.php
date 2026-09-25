<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Resources;

use App\Domains\Accounts\Application\Dtos\PasskeyData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PasskeyData $resource
 */
final class PasskeyRegistrationResource extends JsonResource
{
    /**
     * @return array{id: string, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}
