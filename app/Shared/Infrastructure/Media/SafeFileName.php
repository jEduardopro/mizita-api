<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Media;

use Illuminate\Support\Str;

final class SafeFileName
{
    private const MAXIMUM_LENGTH = 80;

    private const SEPARATOR = '-';

    public static function from(string $fileName, string $fallbackName): string
    {
        $stem = self::slugged((string) pathinfo($fileName, PATHINFO_FILENAME));
        $extension = self::slugged((string) pathinfo($fileName, PATHINFO_EXTENSION));

        $name = mb_substr($stem === '' ? $fallbackName : $stem, 0, self::MAXIMUM_LENGTH);

        return $extension === '' ? $name : $name.'.'.$extension;
    }

    private static function slugged(string $value): string
    {
        $transliterated = Str::ascii(mb_strtolower($value));

        return trim((string) preg_replace('/[^a-z0-9]+/', self::SEPARATOR, $transliterated), self::SEPARATOR);
    }
}
