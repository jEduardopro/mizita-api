<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Resources;

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuthenticatedAccountData $resource
 */
final class AccessTokenResource extends JsonResource
{
    public function __construct(
        AuthenticatedAccountData $account,
        private readonly string $token,
    ) {
        parent::__construct($account);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'account' => AccountResource::make($this->resource),
        ];
    }
}
