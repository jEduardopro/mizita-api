<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionBusinessSlug;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionEndDate;
use App\Domains\Subscriptions\ValueObjects\BusinessSlug;
use App\Domains\Subscriptions\ValueObjects\LastIncludedDay;

final readonly class ExtendSubscriptionInput
{
    public function __construct(
        public string $businessSlug,
        public string $until,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            businessSlug: (string) ($payload['business'] ?? ''),
            until: (string) ($payload['until'] ?? ''),
        );
    }

    /**
     * @throws InvalidSubscriptionBusinessSlug
     * @throws InvalidSubscriptionEndDate
     */
    public function validate(): void
    {
        $this->validateBusinessSlug();
        $this->validateUntil();
    }

    public function businessSlug(): BusinessSlug
    {
        return BusinessSlug::fromString($this->businessSlug);
    }

    public function lastIncludedDay(): LastIncludedDay
    {
        return LastIncludedDay::fromString($this->until);
    }

    private function validateBusinessSlug(): void
    {
        $this->businessSlug();
    }

    private function validateUntil(): void
    {
        $this->lastIncludedDay();
    }
}
