<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Resources;

use App\Domains\PublicCatalog\Application\Dtos\PublicBusinessPageData;
use App\Domains\PublicCatalog\ValueObjects\PublicBrand;
use App\Domains\PublicCatalog\ValueObjects\PublicContact;
use App\Domains\PublicCatalog\ValueObjects\PublicGalleryImage;
use App\Domains\PublicCatalog\ValueObjects\PublicLink;
use App\Domains\PublicCatalog\ValueObjects\PublicLocation;
use App\Domains\PublicCatalog\ValueObjects\PublicScheduleEntry;
use App\Domains\PublicCatalog\ValueObjects\PublicService;
use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PublicBusinessPageData $resource
 */
final class PublicBusinessPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->resource->profile;

        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'slug' => $profile->slug,
            'about' => $profile->about,
            'timezone' => $profile->timezone,
            'currency_code' => $profile->currencyCode,
            'logo_url' => $profile->logoUrl,
            'brand' => self::describeBrand($this->resource->brand),
            'schedule' => array_map(self::describeScheduleEntry(...), $this->resource->schedule),
            'services' => array_map(self::describeService(...), $this->resource->services),
            'team' => array_map(self::describeTeamMember(...), $this->resource->team),
            'location' => self::describeLocation($this->resource->location),
            'contact' => self::describeContact($this->resource->contact),
        ];
    }

    /**
     * @return array{accent_color: string, button_shape: string, theme: string, banner_url: string|null, gallery: list<array{id: string, url: string}>}
     */
    private static function describeBrand(PublicBrand $brand): array
    {
        return [
            'accent_color' => $brand->accentColor,
            'button_shape' => $brand->buttonShape,
            'theme' => $brand->theme,
            'banner_url' => $brand->bannerUrl,
            'gallery' => array_map(self::describeGalleryImage(...), $brand->gallery),
        ];
    }

    /**
     * @return array{id: string, url: string}
     */
    private static function describeGalleryImage(PublicGalleryImage $image): array
    {
        return [
            'id' => $image->id,
            'url' => $image->url,
        ];
    }

    /**
     * @return array{weekday: int, starts_at: string, ends_at: string}
     */
    private static function describeScheduleEntry(PublicScheduleEntry $entry): array
    {
        return [
            'weekday' => $entry->weekday,
            'starts_at' => $entry->startsAt,
            'ends_at' => $entry->endsAt,
        ];
    }

    /**
     * @return array{id: string, name: string, slug: string, description: string|null, duration_minutes: int, price: string, image_url: string|null}
     */
    private static function describeService(PublicService $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'duration_minutes' => $service->durationMinutes,
            'price' => $service->price,
            'image_url' => $service->imageUrl,
        ];
    }

    /**
     * @return array{id: string, name: string}
     */
    private static function describeTeamMember(PublicTeamMember $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
        ];
    }

    /**
     * @return array{street: string, city: string, state: string|null, postal_code: string, country_code: string, latitude: string|null, longitude: string|null}|null
     */
    private static function describeLocation(?PublicLocation $location): ?array
    {
        if ($location === null) {
            return null;
        }

        return [
            'street' => $location->street,
            'city' => $location->city,
            'state' => $location->state,
            'postal_code' => $location->postalCode,
            'country_code' => $location->countryCode,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ];
    }

    /**
     * @return array{phone: string|null, links: list<array{platform: string, url: string}>}
     */
    private static function describeContact(PublicContact $contact): array
    {
        return [
            'phone' => $contact->phone,
            'links' => array_map(self::describeLink(...), $contact->links),
        ];
    }

    /**
     * @return array{platform: string, url: string}
     */
    private static function describeLink(PublicLink $link): array
    {
        return [
            'platform' => $link->platform,
            'url' => $link->url,
        ];
    }
}
