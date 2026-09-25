<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Resources;

use App\Domains\Staff\Application\Dtos\TeamMemberRemovalData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TeamMemberRemovalData $resource
 */
final class TeamMemberRemovalResource extends JsonResource
{
    /**
     * @return array{removable: bool, blocker: 'owner'|'upcoming_appointments'|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'removable' => $this->resource->removable(),
            'blocker' => $this->resource->blocker?->value,
        ];
    }
}
