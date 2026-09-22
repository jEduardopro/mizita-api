<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NullResource extends JsonResource
{
    public static function instance(): self
    {
        return new self(null);
    }

    /**
     * @return array<string, null>
     */
    public function toArray(Request $request): array
    {
        return [self::$wrap => null];
    }
}
