<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Eloquent\Casts;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<DateTimeImmutable, DateTimeInterface|string>
 */
final class UtcInstant implements CastsAttributes
{
    private const TIMEZONE = 'UTC';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?DateTimeImmutable
    {
        return self::inUtc($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::inUtc($value)?->format(DATE_ATOM);
    }

    private static function inUtc(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $instant = $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable((string) $value);

        return $instant->setTimezone(new DateTimeZone(self::TIMEZONE));
    }
}
