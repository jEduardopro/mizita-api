<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use DateTimeZone;

final readonly class Timezone
{
    private function __construct(
        public string $value,
    ) {}

    public static function restore(string $value): self
    {
        return new self($value);
    }

    /**
     * Accepts backward compatible aliases such as Asia/Calcutta, which browsers report and plain
     * ALL excludes, matching the timezone:all_with_bc rule in CreateBusinessRequest.
     *
     * @throws InvalidBusinessTimezone
     */
    public static function fromString(string $value): self
    {
        $candidate = trim($value);

        if (! in_array($candidate, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw InvalidBusinessTimezone::forValue($value);
        }

        return new self((new DateTimeZone($candidate))->getName());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
