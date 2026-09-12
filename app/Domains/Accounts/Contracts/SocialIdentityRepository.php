<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Exceptions\SocialIdentityAlreadyLinked;
use App\Domains\Accounts\ValueObjects\SocialProvider;

/**
 * Port for SocialIdentity persistence. It speaks entities, so the uuid to int
 * translation the schema needs stays inside the adapter.
 */
interface SocialIdentityRepository
{
    /**
     * Null means "this provider user has never been linked here", which is the
     * branch that decides between signing in and registering.
     */
    public function findByProviderUserId(SocialProvider $provider, string $providerUserId): ?SocialIdentity;

    /**
     * @throws SocialIdentityAlreadyLinked when a concurrent writer linked the
     *                                     same provider user between the caller's lookup and this write
     */
    public function save(SocialIdentity $identity): void;
}
