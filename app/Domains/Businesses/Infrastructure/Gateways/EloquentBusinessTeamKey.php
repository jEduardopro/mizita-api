<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Shared\Contracts\BusinessTeamKey;

/**
 * Reads the internal key of a business straight from its table.
 *
 * It lives in Businesses because Businesses owns that table, and it is a
 * gateway rather than a repository method because it answers in a persistence
 * key: the int primary key is exactly what BusinessRepository exists to keep
 * out of the domain, so it cannot travel on the port that speaks entities.
 *
 * Soft deleted businesses are excluded by the model's scope. A membership at a
 * deleted business is not operable, so scoping permissions to its team would
 * grant something the tenant resolver has already refused.
 */
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
