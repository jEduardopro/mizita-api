<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects\Payloads;

use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

final readonly class SnapshotReader
{
    private const STORAGE_TIMEZONE = 'UTC';

    private const MISSING_TEXT = '';

    /**
     * @param  array<array-key, mixed>  $fields
     */
    private function __construct(
        private array $fields,
    ) {}

    /**
     * @param  array<array-key, mixed>  $fields
     */
    public static function of(array $fields): self
    {
        return new self($fields);
    }

    public function section(string $key): self
    {
        $section = $this->fields[$key] ?? [];

        return new self(is_array($section) ? $section : []);
    }

    public function text(string $key): string
    {
        $value = $this->fields[$key] ?? self::MISSING_TEXT;

        return is_scalar($value) ? (string) $value : self::MISSING_TEXT;
    }

    public function instant(string $key): DateTimeImmutable
    {
        $instant = DateTimeImmutable::createFromFormat(DATE_ATOM, $this->text($key));

        if ($instant === false) {
            throw new UnexpectedValueException("Notification snapshot field [{$key}] is not a DATE_ATOM instant.");
        }

        return $instant->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE));
    }
}
