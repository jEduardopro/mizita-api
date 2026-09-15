<?php

declare(strict_types=1);

namespace App\Domains\Services\Services;

final class BookingLinks
{
    private const PUBLIC_PREFIX = '/b/';

    private const SEPARATOR = '/';

    public function __construct(
        private readonly string $baseUrl,
    ) {}

    public function forService(string $businessSlug, string $serviceSlug): string
    {
        return rtrim($this->baseUrl, self::SEPARATOR)
            .self::PUBLIC_PREFIX
            .$businessSlug
            .self::SEPARATOR
            .$serviceSlug;
    }
}
