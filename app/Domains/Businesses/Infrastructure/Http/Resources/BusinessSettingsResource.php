<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Resources;

use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\ValueObjects\BookingPageImageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPageSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessAddressSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use App\Shared\ValueObjects\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BusinessSettingsData $resource
 */
final class BusinessSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'industry_id' => $this->resource->industryId,
            'timezone' => $this->resource->timezone,
            'about' => $this->resource->about,
            'contact_email' => $this->resource->contactEmail,
            'currency_code' => $this->resource->currencyCode,
            'logo_url' => $this->resource->logoUrl,
            'phone' => self::describePhone($this->resource->phone),
            'address' => self::describeAddress($this->resource->address),
            'schedule' => array_map(self::describeScheduleEntry(...), $this->resource->schedule),
            'links' => array_map(self::describeLink(...), $this->resource->links),
            'booking_page' => self::describeBookingPage($this->resource->bookingPage),
        ];
    }

    /**
     * @return array{country_code: string, national_number: string, e164: string}|null
     */
    private static function describePhone(?PhoneNumber $phone): ?array
    {
        if ($phone === null) {
            return null;
        }

        return [
            'country_code' => $phone->country()->value,
            'national_number' => $phone->nationalNumber(),
            'e164' => $phone->e164(),
        ];
    }

    /**
     * @return array{street: string, city: string|null, state_id: string|null, postal_code: string|null, country_code: string, latitude: string|null, longitude: string|null}|null
     */
    private static function describeAddress(?BusinessAddressSnapshot $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'street' => $address->street,
            'city' => $address->city,
            'state_id' => $address->stateId,
            'postal_code' => $address->postalCode,
            'country_code' => $address->countryCode,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ];
    }

    /**
     * @return array{weekday: int, starts_at: string, ends_at: string}
     */
    private static function describeScheduleEntry(BusinessScheduleEntry $entry): array
    {
        return [
            'weekday' => $entry->weekday,
            'starts_at' => $entry->startsAt,
            'ends_at' => $entry->endsAt,
        ];
    }

    /**
     * @return array{platform: string, url: string, position: int}
     */
    private static function describeLink(BusinessLinkSnapshot $link): array
    {
        return [
            'platform' => $link->platform,
            'url' => $link->url,
            'position' => $link->position,
        ];
    }

    /**
     * @return array{accent_color: string, button_shape: string, theme: string, banner_url: string|null, gallery: list<array{id: string, url: string}>}
     */
    private static function describeBookingPage(BookingPageSnapshot $bookingPage): array
    {
        return [
            'accent_color' => $bookingPage->accentColor,
            'button_shape' => $bookingPage->buttonShape,
            'theme' => $bookingPage->theme,
            'banner_url' => $bookingPage->bannerUrl,
            'gallery' => array_map(self::describeGalleryImage(...), $bookingPage->gallery),
        ];
    }

    /**
     * @return array{id: string, url: string}
     */
    private static function describeGalleryImage(BookingPageImageSnapshot $image): array
    {
        return [
            'id' => $image->id,
            'url' => $image->url,
        ];
    }
}
