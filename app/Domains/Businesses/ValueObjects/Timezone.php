<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use DateTimeZone;

/**
 * The single source of local time for a business: opening hours are local facts,
 * appointments are absolute instants, and the offset between them changes twice
 * a year. Storing the identifier - never an offset, never the application
 * default - is what makes that conversion possible across a DST boundary.
 *
 * Moves to app/Shared/ValueObjects/ the day Availability becomes a second
 * consumer. Promoting it earlier would be coupling dressed up as reuse.
 */
final readonly class Timezone
{
    private function __construct(
        public string $value,
    ) {}

    /**
     * Callable by a mapper and by nothing else. A row the database already
     * accepted has to stay readable: a tzdata update is enough to retire an
     * identifier, and failing here would make that business impossible to load
     * at all rather than merely wrong about its opening hours.
     */
    public static function restore(string $value): self
    {
        return new self($value);
    }

    /**
     * Accepts backward compatible aliases, because a browser reporting
     * Asia/Calcutta is reporting a zone that works and refusing it would lock
     * that person out of onboarding. CreateBusinessRequest validates with
     * timezone:all_with_bc so the two agree on what a time zone is.
     *
     * Membership is checked rather than just constructing a DateTimeZone
     * because the constructor is case insensitive where the identifier list is
     * not, so "europe/madrid" would be stored as typed and fail every later
     * comparison against "Europe/Madrid".
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
