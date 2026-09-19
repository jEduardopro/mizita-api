<?php

declare(strict_types=1);

namespace App\Domains\Services\ValueObjects;

use App\Domains\Services\Exceptions\InvalidServiceSlug;
use App\Domains\Services\Exceptions\ServiceNameNotSluggable;
use InvalidArgumentException;

final readonly class Slug
{
    public const FIRST_SUFFIX = 2;

    private const MAXIMUM_LENGTH = 60;

    private const SEPARATOR = '-';

    private const SHAPE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @var list<string>
     */
    private const RESERVED = [
        'book',
    ];

    /**
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

    public static function tryFromName(string $name): ?self
    {
        $value = self::normalize($name);

        if ($value === '' || self::isReserved($value)) {
            return null;
        }

        return new self($value);
    }

    /**
     * @throws ServiceNameNotSluggable
     */
    public static function fromName(string $name): self
    {
        return self::tryFromName($name) ?? throw ServiceNameNotSluggable::forName($name);
    }

    public static function restore(string $value): self
    {
        return new self($value);
    }

    /**
     * @throws InvalidServiceSlug
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::SHAPE, $value) !== 1 || strlen($value) > self::MAXIMUM_LENGTH) {
            throw InvalidServiceSlug::forValue($value);
        }

        if (self::isReserved($value)) {
            throw InvalidServiceSlug::forValue($value);
        }

        return new self($value);
    }

    /**
     * @throws InvalidArgumentException
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

        $hyphenated = preg_replace('/[^a-z0-9]+/u', self::SEPARATOR, $folded) ?? '';

        return self::truncate(trim($hyphenated, self::SEPARATOR));
    }

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
