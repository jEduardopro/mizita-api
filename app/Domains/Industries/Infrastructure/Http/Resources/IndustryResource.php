<?php

declare(strict_types=1);

namespace App\Domains\Industries\Infrastructure\Http\Resources;

use App\Domains\Industries\Application\Dtos\IndustryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IndustryData $resource
 */
final class IndustryResource extends JsonResource
{
    /**
     * Deliberately no label: the human-readable name is a translation, keyed by
     * "key" in the frontend's i18next "industries" namespace, so a new language
     * is a JSON file on the client and never a migration here.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'position' => $this->resource->position,
        ];
    }
}
