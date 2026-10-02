<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Resources;

use App\Domains\Platform\Application\Dtos\PlatformBusinessData;
use App\Domains\Platform\ValueObjects\PlatformBusinessOwner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PlatformBusinessData $resource
 */
final class PlatformBusinessResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, slug: string, created_at: string, owner: array{name: string, email: string}|null, services_count: int, customers_count: int, plan: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
            'owner' => self::describeOwner($this->resource->owner),
            'services_count' => $this->resource->servicesCount,
            'customers_count' => $this->resource->customersCount,
            'plan' => $this->resource->plan->value,
        ];
    }

    /**
     * @return array{name: string, email: string}|null
     */
    private static function describeOwner(?PlatformBusinessOwner $owner): ?array
    {
        if ($owner === null) {
            return null;
        }

        return [
            'name' => $owner->name,
            'email' => $owner->email,
        ];
    }
}
