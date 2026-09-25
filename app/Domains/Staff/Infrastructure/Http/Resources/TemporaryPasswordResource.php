<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Resources;

use App\Domains\Staff\Application\Dtos\RevealedTemporaryPassword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read RevealedTemporaryPassword $resource
 */
final class TemporaryPasswordResource extends JsonResource
{
    /**
     * @return array{temporary_password: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'temporary_password' => $this->resource->temporaryPassword,
        ];
    }
}
