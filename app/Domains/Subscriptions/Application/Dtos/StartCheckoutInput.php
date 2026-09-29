<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPlan;

final readonly class StartCheckoutInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $businessId,
        public string $planId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $businessId): self
    {
        return new self(
            businessId: $businessId,
            planId: (string) ($payload['plan_id'] ?? ''),
        );
    }

    /**
     * @throws InvalidSubscriptionPlan
     */
    public function validate(): void
    {
        $this->validatePlanId();
    }

    private function validatePlanId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->planId) !== 1) {
            throw InvalidSubscriptionPlan::unknown($this->planId);
        }
    }
}
