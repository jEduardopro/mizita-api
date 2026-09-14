<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;

final readonly class SignInWithGoogleIdTokenInput
{
    public function __construct(
        public string $idToken,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            idToken: self::textOrEmpty($payload['id_token'] ?? null),
        );
    }

    /**
     * @throws InvalidGoogleIdToken
     */
    public function validate(): void
    {
        $this->validateIdToken();
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateIdToken(): void
    {
        if (trim($this->idToken) === '') {
            throw InvalidGoogleIdToken::notAJsonWebToken();
        }
    }
}
