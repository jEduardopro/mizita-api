<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;

final readonly class StaffPageSlug
{
    public const MAXIMUM_LENGTH = 60;

    private const SHAPE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws StaffBookingPageNotFound
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::SHAPE, $value) !== 1 || strlen($value) > self::MAXIMUM_LENGTH) {
            throw StaffBookingPageNotFound::withSlug($value);
        }

        return new self($value);
    }
}
