<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionBusinessSlug;

final readonly class BusinessSlug
{
    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidSubscriptionBusinessSlug
     */
    public static function fromString(string $value): self
    {
        $slug = trim($value);

        if ($slug === '') {
            throw InvalidSubscriptionBusinessSlug::missing();
        }

        return new self($slug);
    }
}
