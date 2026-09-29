<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\Exceptions\CheckoutSessionNotFound;

final readonly class ConfirmCheckoutInput
{
    public const MAXIMUM_SESSION_ID_LENGTH = 255;

    private const SESSION_ID_PATTERN = '/^cs_[A-Za-z0-9_]+$/D';

    public function __construct(
        public string $businessId,
        public string $sessionId,
    ) {}

    /**
     * @throws CheckoutSessionNotFound
     */
    public function validate(): void
    {
        $this->validateSessionId();
    }

    private function validateSessionId(): void
    {
        if (strlen($this->sessionId) > self::MAXIMUM_SESSION_ID_LENGTH
            || preg_match(self::SESSION_ID_PATTERN, $this->sessionId) !== 1) {
            throw CheckoutSessionNotFound::withId($this->sessionId);
        }
    }
}
