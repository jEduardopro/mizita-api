<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

use App\Domains\Businesses\Exceptions\BusinessNameNotSluggable;
use App\Domains\Businesses\Exceptions\InvalidBusinessSlug;
use InvalidArgumentException;

/**
 * The public web address of a business, derived from its name. Everything that
 * emits one goes through here, which is what lets the repository state as an
 * invariant that a slug never contains a LIKE wildcard.
 *
 * The normaliser uses an explicit table rather than Str::slug, which Illuminate
 * bans from the domain layer, or iconv, whose output depends on the process
 * locale - under several common ones "á" comes back as `"a`, and a public
 * address that changes with a server setting is not one a business can print.
 */
final readonly class Slug
{
    /** A second business with the same name is "-2" because the first, unsuffixed, is conceptually "-1". */
    public const FIRST_SUFFIX = 2;

    private const MAXIMUM_LENGTH = 60;

    private const SEPARATOR = '-';

    private const SHAPE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * A slug is a first path segment in the making, and one that shadows /admin
     * or /terms is a routing bug waiting for a deploy.
     *
     * @var list<string>
     */
    private const RESERVED = [
        'api',
        'admin',
        'dashboard',
        'onboarding',
        'auth',
        'login',
        'register',
        'logout',
        'sanctum',
        'up',
        'terms',
        'privacy',
        'cookies',
    ];

    /**
     * Keys are lowercase only: the name is folded before the table is applied.
     *
     * @var array<string, string>
     */
    private const TRANSLITERATIONS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ñ' => 'n',
        'ç' => 'c',
        'æ' => 'ae',
        'œ' => 'oe',
        'ß' => 'ss',
    ];

    private function __construct(
        public string $value,
    ) {}

    /**
     * The nullable twin exists so the availability endpoint can answer "that
     * name will not work" without throwing for a question callers are expected
     * to ask.
     */
    public static function tryFromName(string $name): ?self
    {
        $value = self::normalize($name);

        if ($value === '' || self::isReserved($value)) {
            return null;
        }

        return new self($value);
    }

    /**
     * @throws BusinessNameNotSluggable when the name leaves nothing behind
     */
    public static function fromName(string $name): self
    {
        return self::tryFromName($name) ?? throw BusinessNameNotSluggable::forName($name);
    }

    /**
     * Callable by a mapper and by nothing else. A slug written before this class
     * existed is still that business's address, and refusing to load it would
     * turn a lax old write into a record nobody can open. The rule is enforced
     * on the way in, by fromName and fromString.
     */
    public static function restore(string $value): self
    {
        return new self($value);
    }

    /**
     * For anything that is not a rehydration - an import, a console command, a
     * future edit endpoint.
     *
     * @throws InvalidBusinessSlug when the value is not one this class could have produced
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::SHAPE, $value) !== 1 || strlen($value) > self::MAXIMUM_LENGTH) {
            throw InvalidBusinessSlug::forValue($value);
        }

        if (self::isReserved($value)) {
            throw InvalidBusinessSlug::reserved($value);
        }

        return new self($value);
    }

    /**
     * Truncating the base first means a slug at the length limit yields a
     * shortened variant rather than "base-2", which no longer matches the base
     * the allocator searched for. Two businesses with near-identical 60
     * character names can therefore both be offered it; the partial unique
     * index settles that, and the loser is told the address is taken.
     *
     * @throws InvalidArgumentException when asked for a suffix below the first one
     */
    public function withSuffix(int $n): self
    {
        if ($n < self::FIRST_SUFFIX) {
            throw new InvalidArgumentException(
                'A slug suffix starts at '.self::FIRST_SUFFIX.", got [{$n}]."
            );
        }

        $suffix = self::SEPARATOR.$n;
        $base = rtrim(
            substr($this->value, 0, self::MAXIMUM_LENGTH - strlen($suffix)),
            self::SEPARATOR,
        );

        return new self($base.$suffix);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function normalize(string $name): string
    {
        $folded = strtr(mb_strtolower(trim($name), 'UTF-8'), self::TRANSLITERATIONS);

        // Null when the name is not valid UTF-8, which is a slug of nothing.
        $hyphenated = preg_replace('/[^a-z0-9]+/u', self::SEPARATOR, $folded) ?? '';

        return self::truncate(trim($hyphenated, self::SEPARATOR));
    }

    /** Cuts at the last word boundary that fits; a single word longer than the limit is clipped. */
    private static function truncate(string $value): string
    {
        if (strlen($value) <= self::MAXIMUM_LENGTH) {
            return $value;
        }

        $clipped = substr($value, 0, self::MAXIMUM_LENGTH);
        $lastBoundary = strrpos($clipped, self::SEPARATOR);

        if ($lastBoundary === false || $lastBoundary === 0) {
            return $clipped;
        }

        return substr($clipped, 0, $lastBoundary);
    }

    private static function isReserved(string $value): bool
    {
        return in_array($value, self::RESERVED, true);
    }
}
