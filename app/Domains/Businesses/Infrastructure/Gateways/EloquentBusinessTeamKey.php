<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Shared\Contracts\BusinessTeamKey;

final class EloquentBusinessTeamKey implements BusinessTeamKey
{
    /**
     * @throws BusinessNotFound
     */
    public function teamKeyFor(string $businessId): int
    {
        $key = BusinessModel::query()
            ->where('uuid', $businessId)
            ->value('id');

        if ($key === null) {
            throw BusinessNotFound::withId($businessId);
        }

        return (int) $key;
    }
}
