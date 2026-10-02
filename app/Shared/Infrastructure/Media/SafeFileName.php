<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Media;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Mime\MimeTypes;

final class SafeFileName
{
    private const MAXIMUM_LENGTH = 80;

    private const SEPARATOR = '-';

    private const EXTENSION_BY_MIME_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function from(string $sourcePath, string $fileName, string $fallbackName): string
    {
        $stem = self::slugged((string) pathinfo($fileName, PATHINFO_FILENAME));
        $name = mb_substr($stem === '' ? $fallbackName : $stem, 0, self::MAXIMUM_LENGTH);

        return $name.'.'.self::extensionOf($sourcePath);
    }

    private static function extensionOf(string $sourcePath): string
    {
        $mimeType = (string) MimeTypes::getDefault()->guessMimeType($sourcePath);

        if (! array_key_exists($mimeType, self::EXTENSION_BY_MIME_TYPE)) {
            throw new InvalidArgumentException("An upload sniffed as [{$mimeType}] has no allowed image extension.");
        }

        return self::EXTENSION_BY_MIME_TYPE[$mimeType];
    }

    private static function slugged(string $value): string
    {
        $transliterated = Str::ascii(mb_strtolower($value));

        return trim((string) preg_replace('/[^a-z0-9]+/', self::SEPARATOR, $transliterated), self::SEPARATOR);
    }
}
