<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use DateTimeImmutable;
use SensitiveParameter;

final readonly class CalendarTokens
{
    public function __construct(
        #[SensitiveParameter] public string $accessToken,
        #[SensitiveParameter] public string $refreshToken,
        public DateTimeImmutable $accessTokenExpiresAt,
    ) {}

    /**
     * @return array{access_token_expires_at: string}
     */
    public function __debugInfo(): array
    {
        return ['access_token_expires_at' => $this->accessTokenExpiresAt->format(DATE_ATOM)];
    }
}
