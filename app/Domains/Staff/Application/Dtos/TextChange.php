<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

final readonly class TextChange
{
    public function __construct(
        public ?string $value,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload, string $key): ?self
    {
        if (! array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];

        return new self(is_string($value) && trim($value) !== '' ? $value : null);
    }
}
