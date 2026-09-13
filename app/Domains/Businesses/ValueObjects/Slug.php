<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

use App\Domains\Businesses\Exceptions\BusinessNameNotSluggable;
use App\Domains\Businesses\Exceptions\InvalidBusinessSlug;
use InvalidArgumentException;

/**
 * The public web address of a business, derived from its name.
 *
 * A slug is a rule attached to a string - an alphabet, a length, a list of
 * words it may not be - so it is a value object rather than validation repeated
 * wherever a slug is written. Everything that emits one goes through here,
 * which is what lets the repository state as an invariant that a slug never
 * contains a LIKE wildcard.
 *
 * Two deliberate omissions in the normaliser:
 *
 * - Str::slug is not used. Illuminate is banned in the domain layer, and this
 *   rule is too load-bearing to reach for the framework anyway.
 * - iconv transliteration is not used. Its output depends on the process
 *   locale, and under several common ones "á" comes back as the two characters
 *   `"a` - a public address that changes with a server setting is not a thing
 *   a business can print.
 *
 * What replaces both is an explicit table: what it does not cover is dropped,
 * visibly, instead of being mangled differently on someone else's machine.
 */
final readonly class Slug
{
    /**
     * The first suffix an allocator may append. A second business with the same
     * name is "-2" because the first one, unsuffixed, is conceptually "-1".
     */
    public const FIRST_SUFFIX = 2;

    private const MAXIMUM_LENGTH = 60;

    private const SEPARATOR = '-';

    /** Lowercase alphanumeric groups joined by single hyphens, no leading or trailing hyphen. */
    private const SHAPE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Words a business may not take, because the platform already answers on
     * them or intends to. A slug is a first path segment in the making, and one
     * that shadows /admin or /terms is a routing bug waiting for a deploy.
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
     * Spanish first, then the accents the rest of western Europe writes, since
     * those are the alphabets the product sells into. Keys are lowercase only:
     * the name is folded before the table is applied.
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
     * The slug a name would produce, or null when nothing usable survives.
     *
     * The nullable twin exists so the availability endpoint can answer "that
     * name will not work" without an exception being thrown for a question a
     * caller is expected to ask.
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
     * Rebuilds a stored slug without checking it, for the rehydration path.
     *
     * Callable by a mapper and by nothing else. Reading a row is not the moment
     * to enforce a rule the row predates: a slug written before this class
     * existed is still that business's address, and refusing to load it would
     * turn a lax old write into a record nobody can open - a worse failure than
     * the one the invariant prevents. The rule is enforced on the way in, which
     * is where it belongs: see fromName and fromString.
     */
    public static function restore(string $value): self
    {
        return new self($value);
    }

    /**
     * Checks a slug that arrived as a string, for anything that is not a
     * rehydration - an import, a console command, a future edit endpoint.
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
     * The same slug disambiguated by a number.
     *
     * The base is truncated first so the result still fits, which means a slug
     * at the length limit does not produce "base-2" but a shortened variant of
     * it. That variant no longer matches the base the allocator searched for,
     * so two businesses with near-identical 60 character names can both be
     * offered it; the partial unique index is what settles that, and the loser
     * is told the address is taken.
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

    /**
     * Folds case, transliterates, then keeps only the slug alphabet.
     *
     * The + quantifier is what collapses runs: any stretch of characters that
     * are not alphanumeric becomes exactly one hyphen, so punctuation, spaces
     * and hyphens the owner typed all reduce to the same separator.
     */
    private static function normalize(string $name): string
    {
        $folded = strtr(mb_strtolower(trim($name), 'UTF-8'), self::TRANSLITERATIONS);

        // Null when the name is not valid UTF-8, which is a slug of nothing.
        $hyphenated = preg_replace('/[^a-z0-9]+/u', self::SEPARATOR, $folded) ?? '';

        return self::truncate(trim($hyphenated, self::SEPARATOR));
    }

    /**
     * Cuts at the last word boundary that fits, so a shortened address still
     * reads as words rather than ending mid-syllable. A single word longer than
     * the limit has no boundary to cut at and is simply clipped.
     */
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
