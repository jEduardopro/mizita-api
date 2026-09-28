<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionBusinessSlug;
use App\Domains\Subscriptions\ValueObjects\BusinessSlug;

final readonly class CancelSubscriptionInput
{
    public function __construct(
        public string $businessSlug,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            businessSlug: (string) ($payload['business'] ?? ''),
        );
    }

    /**
     * @throws InvalidSubscriptionBusinessSlug
     */
    public function validate(): void
    {
        $this->validateBusinessSlug();
    }

    public function businessSlug(): BusinessSlug
    {
        return BusinessSlug::fromString($this->businessSlug);
    }

    private function validateBusinessSlug(): void
    {
        $this->businessSlug();
    }
}
