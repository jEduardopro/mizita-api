<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use DateTimeZone;

/**
 * The IANA time zone a business keeps its local time in.
 *
 * It is the single source of local time for everything the business publishes:
 * opening hours are local facts, appointments are absolute instants, and the
 * offset between them changes twice a year. Storing the identifier - never an
 * offset, never the application default - is what makes that conversion
 * possible on the far side of a daylight saving boundary.
 *
 * Moves to app/Shared/ValueObjects/ the day Availability becomes a second
 * consumer. Until then it is one domain's rule, and promoting it early would be
 * coupling dressed up as reuse.
 */
final readonly class Timezone
{
    private function __construct(
        public string $value,
    ) {}

    /**
     * Rebuilds a stored identifier without checking it, for the rehydration
     * path.
     *
     * Callable by a mapper and by nothing else. A row the database already
     * accepted has to stay readable: failing here would make a business that
     * was written with a zone this class no longer recognises - a tzdata
     * update is enough - impossible to load at all, instead of merely wrong
     * about its opening hours. The check lives on the way in.
     */
    public static function restore(string $value): self
    {
        return new self($value);
    }

    /**
     * Accepts any identifier from PHP's full list, backward compatible aliases
     * included: a browser reporting Asia/Calcutta is reporting a zone that
     * works, and refusing it would lock that person out of onboarding. The
     * create request validates with timezone:all_with_bc for the same reason,
     * so the two agree on what a time zone is.
     *
     * Matching is exact, which is the point of checking membership rather than
     * just constructing a DateTimeZone: the constructor is case insensitive
     * where the identifier list is not, so "europe/madrid" would otherwise be
     * stored as typed and fail every later comparison against "Europe/Madrid".
     *
     * The value is read back from DateTimeZone afterwards. That does not
     * rewrite an alias to its modern spelling - PHP returns the identifier it
     * was given - it guarantees the string stored is one PHP can resolve.
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
