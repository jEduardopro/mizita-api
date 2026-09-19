<?php

declare(strict_types=1);

namespace Tests\Support\BookingPolicies;

use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\Exceptions\BookingPolicyNotFound;

final class FakeBookingPolicyRepository implements BookingPolicyRepository
{
    /**
     * @var array<string, BookingPolicy>
     */
    private array $policies = [];

    /**
     * @var list<BookingPolicy>
     */
    public array $saved = [];

    /**
     * @var list<string>
     */
    public array $businessIdsSeen = [];

    /**
     * @var list<string>
     */
    public array $deleted = [];

    public function store(BookingPolicy ...$policies): self
    {
        foreach ($policies as $policy) {
            $this->policies[$policy->businessId] = $policy;
        }

        return $this;
    }

    public function findForBusiness(string $businessId): ?BookingPolicy
    {
        $this->businessIdsSeen[] = $businessId;

        return $this->policies[$businessId] ?? null;
    }

    public function save(BookingPolicy $policy): void
    {
        $this->policies[$policy->businessId] = $policy;
        $this->saved[] = $policy;
    }

    public function delete(string $id): void
    {
        foreach ($this->policies as $businessId => $policy) {
            if ($policy->id !== $id) {
                continue;
            }

            unset($this->policies[$businessId]);
            $this->deleted[] = $id;

            return;
        }

        throw BookingPolicyNotFound::withId($id);
    }
}
