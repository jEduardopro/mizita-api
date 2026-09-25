<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Resources;

use App\Domains\Accounts\Application\Dtos\PasskeyData;
use App\Domains\Accounts\Application\Dtos\SignInSecurityData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SignInSecurityData $resource
 */
final class SignInSecurityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'has_password' => $this->resource->hasPassword,
            'two_factor' => $this->resource->twoFactor->value,
            'passkeys' => array_map($this->passkeyToArray(...), $this->resource->passkeys),
        ];
    }

    /**
     * @return array{id: string, name: string, authenticator: string|null, created_at: string, last_used_at: string|null}
     */
    private function passkeyToArray(PasskeyData $passkey): array
    {
        return [
            'id' => $passkey->id,
            'name' => $passkey->name,
            'authenticator' => $passkey->authenticator,
            'created_at' => $passkey->createdAt->format(DATE_ATOM),
            'last_used_at' => $passkey->lastUsedAt?->format(DATE_ATOM),
        ];
    }
}
