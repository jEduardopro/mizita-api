<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Http\Resources;

use App\Domains\Integrations\Application\Dtos\AuthorizationUrlData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuthorizationUrlData $resource
 */
final class AuthorizationUrlResource extends JsonResource
{
    /**
     * @return array{authorization_url: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'authorization_url' => $this->resource->authorizationUrl,
        ];
    }
}
