<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Contracts\BusinessOwnerAccounts;
use App\Domains\Platform\Exceptions\ImpersonatedBusinessNotFound;
use App\Domains\Platform\ValueObjects\BusinessOwnerAccount;
use Throwable;

final class FakeBusinessOwnerAccounts implements BusinessOwnerAccounts
{
    /**
     * @var array<string, BusinessOwnerAccount>
     */
    private array $owners = [];

    /**
     * @var array<string, Throwable>
     */
    private array $refusals = [];

    /**
     * @var list<string>
     */
    public array $businessIdsAsked = [];

    public function owning(BusinessOwnerAccount $owner): self
    {
        $this->owners[$owner->businessId] = $owner;

        return $this;
    }

    public function refusing(string $businessId, Throwable $refusal): self
    {
        $this->refusals[$businessId] = $refusal;

        return $this;
    }

    public function ownerOf(string $businessId): BusinessOwnerAccount
    {
        $this->businessIdsAsked[] = $businessId;

        if (isset($this->refusals[$businessId])) {
            throw $this->refusals[$businessId];
        }

        return $this->owners[$businessId] ?? throw ImpersonatedBusinessNotFound::withId($businessId);
    }
}
