<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Http\Resources;

use App\Domains\BookingPages\Application\Dtos\BookingPageData;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BookingPageData $resource
 */
final class BookingPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'accent_color' => $this->resource->accentColor,
            'button_shape' => $this->resource->buttonShape,
            'theme' => $this->resource->theme,
            'banner_url' => $this->resource->bannerUrl,
            'gallery' => array_map(
                static fn (BookingPageImage $image): array => [
                    'id' => $image->id,
                    'url' => $image->url,
                ],
                $this->resource->gallery,
            ),
        ];
    }
}
