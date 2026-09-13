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
     * There is deliberately no label: the human-readable name of an industry is
     * a translation, and translations live in the frontend's i18next
     * "industries" namespace keyed by this "key". That way a new language is a
     * JSON file on the client and never a migration here.
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
