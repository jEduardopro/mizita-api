<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\InvalidBusinessName;

final readonly class CheckBusinessNameAvailabilityInput
{
    private const MINIMUM_NAME_LENGTH = 2;

    private const MAXIMUM_NAME_LENGTH = 120;

    public function __construct(
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            name: self::textOrEmpty($payload['name'] ?? null),
        );
    }

    /**
     * @throws InvalidBusinessName
     */
    public function validate(): void
    {
        $this->validateName();
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateName(): void
    {
        $name = trim($this->name);

        if ($name === '') {
            throw InvalidBusinessName::empty();
        }

        if (mb_strlen($name) < self::MINIMUM_NAME_LENGTH) {
            throw InvalidBusinessName::tooShort($name);
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidBusinessName::tooLong($name);
        }
    }
}
