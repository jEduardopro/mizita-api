<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Resources;

use App\Domains\Accounts\Application\Dtos\AccountReactivatedData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AccountReactivatedData $resource
 */
final class AccountReactivatedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'two_factor_required' => $this->resource->requiresSecondFactor,
        ];
    }
}
