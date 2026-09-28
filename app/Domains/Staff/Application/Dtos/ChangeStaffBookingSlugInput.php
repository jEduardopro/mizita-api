<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\InvalidBookingSlug;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\BookingSlug;
use App\Domains\Staff\ValueObjects\StaffMemberId;

final readonly class ChangeStaffBookingSlugInput
{
    public function __construct(
        public string $staffMemberId,
        public string $slug,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $staffMemberId): self
    {
        $slug = $payload['slug'] ?? '';

        return new self(
            staffMemberId: $staffMemberId,
            slug: is_string($slug) ? $slug : '',
        );
    }

    /**
     * @throws StaffMemberNotFound
     * @throws InvalidBookingSlug
     */
    public function validate(): void
    {
        $this->validateStaffMemberId();
        $this->validateSlug();
    }

    /**
     * @throws InvalidBookingSlug
     */
    public function toBookingSlug(): BookingSlug
    {
        return BookingSlug::fromString(trim($this->slug));
    }

    private function validateStaffMemberId(): void
    {
        StaffMemberId::fromString($this->staffMemberId);
    }

    private function validateSlug(): void
    {
        $this->toBookingSlug();
    }
}
