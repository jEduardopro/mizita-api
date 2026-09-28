<?php

declare(strict_types=1);

namespace App\Domains\Staff\Services;

use App\Domains\Staff\ValueObjects\BookingSlug;

final class BookingLinks
{
    private const TEAM_SEGMENT = 'equipo';

    private const SEPARATOR = '/';

    public function __construct(
        private readonly string $baseUrl,
    ) {}

    public function forStaffMember(string $businessSlug, BookingSlug $bookingSlug): string
    {
        return rtrim($this->baseUrl, self::SEPARATOR)
            .self::SEPARATOR
            .$businessSlug
            .self::SEPARATOR
            .self::TEAM_SEGMENT
            .self::SEPARATOR
            .$bookingSlug->value;
    }
}
