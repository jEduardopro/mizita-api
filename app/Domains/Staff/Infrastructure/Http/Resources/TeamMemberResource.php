<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Resources;

use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\ValueObjects\BookingLinkBlocker;
use App\Shared\ValueObjects\PhoneNumber;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TeamMemberData $resource
 */
final class TeamMemberResource extends JsonResource
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
            'phone' => self::describePhone($this->resource->phone),
            'photo_url' => $this->resource->photoUrl,
            'job_title' => $this->resource->jobTitle,
            'about' => $this->resource->about,
            'booking_slug' => $this->resource->bookingLink->link?->slug,
            'booking_url' => $this->resource->bookingLink->link?->url,
            'booking_link_blockers' => array_map(
                static fn (BookingLinkBlocker $blocker): string => $blocker->value,
                $this->resource->bookingLink->blockers,
            ),
            'level' => $this->resource->level->value,
            'invitation_pending' => $this->resource->invitationPending,
            'temporary_password_available' => $this->resource->temporaryPasswordAvailable,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
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
