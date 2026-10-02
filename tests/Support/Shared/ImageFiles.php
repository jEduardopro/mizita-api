<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use GdImage;
use RuntimeException;

final class ImageFiles
{
    /** @var array<string, string> */
    private static array $created = [];

    public static function png(int $width = 1, int $height = 1): string
    {
        return self::drawn('png', $width, $height, static fn (GdImage $image, string $path): bool => imagepng($image, $path));
    }

    public static function jpeg(int $width = 1, int $height = 1): string
    {
        return self::drawn('jpeg', $width, $height, static fn (GdImage $image, string $path): bool => imagejpeg($image, $path));
    }

    public static function webp(int $width = 1, int $height = 1): string
    {
        return self::drawn('webp', $width, $height, static fn (GdImage $image, string $path): bool => imagewebp($image, $path));
    }

    public static function gif(int $width = 1, int $height = 1): string
    {
        return self::drawn('gif', $width, $height, static fn (GdImage $image, string $path): bool => imagegif($image, $path));
    }

    public static function text(string $contents = 'not an image'): string
    {
        return self::cached('text:'.sha1($contents), static function (string $path) use ($contents): void {
            file_put_contents($path, $contents);
        });
    }

    public static function svg(): string
    {
        return self::text('<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>');
    }

    /**
     * @param  callable(GdImage, string): bool  $encode
     */
    private static function drawn(string $format, int $width, int $height, callable $encode): string
    {
        return self::cached("{$format}:{$width}x{$height}", static function (string $path) use ($width, $height, $encode): void {
            $image = imagecreatetruecolor($width, $height) ?: throw new RuntimeException('GD could not allocate the image.');

            $encode($image, $path) ?: throw new RuntimeException('GD could not encode the image.');
        });
    }

    /**
     * @param  callable(string): void  $write
     */
    private static function cached(string $key, callable $write): string
    {
        if (isset(self::$created[$key]) && is_file(self::$created[$key])) {
            return self::$created[$key];
        }

        $path = tempnam(sys_get_temp_dir(), 'mizita-image-') ?: throw new RuntimeException('No temporary file could be created.');
        $write($path);

        if (self::$created === []) {
            register_shutdown_function(static function (): void {
                array_map(static fn (string $created): bool => @unlink($created), self::$created);
            });
        }

        return self::$created[$key] = $path;
    }
}
