<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Resources;

use App\Domains\Staff\Application\Dtos\MyProfileData;
use App\Domains\Staff\ValueObjects\BookingLinkBlocker;
use App\Shared\ValueObjects\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MyProfileData $resource
 */
final class MyProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'staff_member_id' => $this->resource->staffMemberId,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'job_title' => $this->resource->jobTitle,
            'about' => $this->resource->about,
            'phone' => self::describePhone($this->resource->phone),
            'photo_url' => $this->resource->photoUrl,
            'booking_slug' => $this->resource->bookingLink->link?->slug,
            'booking_url' => $this->resource->bookingLink->link?->url,
            'booking_link_blockers' => array_map(
                static fn (BookingLinkBlocker $blocker): string => $blocker->value,
                $this->resource->bookingLink->blockers,
            ),
            'role' => $this->resource->role->value,
            'has_password' => $this->resource->hasPassword,
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
}
