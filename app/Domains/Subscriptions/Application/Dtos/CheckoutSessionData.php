<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\ValueObjects\CheckoutSession;
use SensitiveParameter;

final readonly class CheckoutSessionData
{
    public function __construct(
        public string $sessionId,
        #[SensitiveParameter] public string $clientSecret,
    ) {}

    public static function fromSession(CheckoutSession $session): self
    {
        return new self(
            sessionId: $session->id,
            clientSecret: $session->clientSecret,
        );
    }
}
