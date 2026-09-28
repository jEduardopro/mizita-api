<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\BookingSlugRegistry;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;

final class FakeBookingSlugRegistry implements BookingSlugRegistry
{
    private const NUMBERED_SUFFIX = '/-[0-9]+$/D';

    /**
     * @var array<string, array<string, string>>
     */
    private array $holders = [];

    /**
     * @var list<array{businessId: string, base: string}>
     */
    public array $matchLookups = [];

    /**
     * @var list<array{businessId: string, bookingSlug: string, staffMemberId: string}>
     */
    public array $holderChecks = [];

    public function held(string $businessId, string $bookingSlug, string $staffMemberId): self
    {
        $this->holders[$businessId][$bookingSlug] = $staffMemberId;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function slugsMatching(string $businessId, string $base): array
    {
        $this->matchLookups[] = ['businessId' => $businessId, 'base' => $base];

        return array_values(array_filter(
            array_map('strval', array_keys($this->holders[$businessId] ?? [])),
            static fn (string $bookingSlug): bool => $bookingSlug === $base || self::isNumberedPrefixOf($bookingSlug, $base),
        ));
    }

    private static function isNumberedPrefixOf(string $bookingSlug, string $base): bool
    {
        return preg_match(self::NUMBERED_SUFFIX, $bookingSlug) === 1
            && str_starts_with($base, (string) preg_replace(self::NUMBERED_SUFFIX, '', $bookingSlug));
    }

    public function isHeldByAnother(string $businessId, string $bookingSlug, string $staffMemberId): bool
    {
        $this->holderChecks[] = ['businessId' => $businessId, 'bookingSlug' => $bookingSlug, 'staffMemberId' => $staffMemberId];

        $holder = $this->holders[$businessId][$bookingSlug] ?? null;

        return $holder !== null && $holder !== $staffMemberId;
    }

    public function staffMemberIdFor(string $businessId, string $bookingSlug): string
    {
        return $this->holders[$businessId][$bookingSlug]
            ?? throw StaffMemberNotFound::withBookingSlug($bookingSlug);
    }
}
