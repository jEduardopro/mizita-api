<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Resources;

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuthenticatedAccountData $resource
 */
final class AccountResource extends JsonResource
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
        ];
    }
}
