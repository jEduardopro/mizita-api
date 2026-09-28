<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionBusinessSlug;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionEndDate;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPlan;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\BusinessSlug;
use App\Domains\Subscriptions\ValueObjects\LastIncludedDay;
use App\Domains\Subscriptions\ValueObjects\Plan;

final readonly class GrantSubscriptionInput
{
    private const WHOLE_AMOUNT = '/^\d{1,15}$/D';

    public function __construct(
        public string $businessSlug,
        public string $until,
        public string $plan = Plan::Complete->value,
        public ?string $amount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        $amount = trim((string) ($payload['amount'] ?? ''));

        return new self(
            businessSlug: (string) ($payload['business'] ?? ''),
            until: (string) ($payload['until'] ?? ''),
            plan: trim((string) ($payload['plan'] ?? Plan::Complete->value)),
            amount: $amount === '' ? null : $amount,
        );
    }

    /**
     * @throws InvalidSubscriptionBusinessSlug
     * @throws InvalidSubscriptionEndDate
     * @throws InvalidSubscriptionPlan
     * @throws InvalidSubscriptionPrice
     */
    public function validate(): void
    {
        $this->validateBusinessSlug();
        $this->validateUntil();
        $this->validatePlan();
        $this->validateAmount();
    }

    public function businessSlug(): BusinessSlug
    {
        return BusinessSlug::fromString($this->businessSlug);
    }

    public function lastIncludedDay(): LastIncludedDay
    {
        return LastIncludedDay::fromString($this->until);
    }

    public function plan(): Plan
    {
        return Plan::tryFrom($this->plan) ?? throw InvalidSubscriptionPlan::unknown($this->plan);
    }

    public function amountInMinorUnits(): ?int
    {
        return $this->amount === null ? null : (int) $this->amount;
    }

    private function validateBusinessSlug(): void
    {
        $this->businessSlug();
    }

    private function validateUntil(): void
    {
        $this->lastIncludedDay();
    }

    private function validatePlan(): void
    {
        $this->plan();
    }

    private function validateAmount(): void
    {
        if ($this->amount !== null && preg_match(self::WHOLE_AMOUNT, $this->amount) !== 1) {
            throw InvalidSubscriptionPrice::malformed($this->amount);
        }
    }
}
