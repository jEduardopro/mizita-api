<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Authorization;

final class AuthorizationNaming
{
    private const SEPARATORS = ['.', '_'];

    private const SLUG_SEPARATOR = '-';

    public static function slugFor(string $name): string
    {
        return str_replace(self::SEPARATORS, self::SLUG_SEPARATOR, $name);
    }

    public static function descriptionKeyFor(string $namespace, string $name): string
    {
        return $namespace.'.'.$name.'.description';
    }
}
