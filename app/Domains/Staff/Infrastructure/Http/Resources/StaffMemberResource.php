<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Resources;

use App\Domains\Staff\Application\Dtos\StaffMemberSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StaffMemberSummary $resource
 */
final class StaffMemberResource extends JsonResource
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
            'role' => $this->resource->role->value,
        ];
    }
}
