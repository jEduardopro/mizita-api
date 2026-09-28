<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Services;

use App\Domains\Services\Contracts\ServiceAllowance;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Exceptions\ActiveServiceLimitReached;

final class ActiveServiceQuota
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceAllowance $allowance,
    ) {}

    /**
     * @throws ActiveServiceLimitReached
     */
    public function ensureRoomFor(string $businessId): void
    {
        $limit = $this->allowance->activeServiceLimitFor($businessId);

        if ($limit === null) {
            return;
        }

        $this->services->lockActivationsOf($businessId);

        if ($this->services->countActive($businessId) >= $limit) {
            throw ActiveServiceLimitReached::of($limit);
        }
    }
}
