<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Exceptions\SocialIdentityAlreadyLinked;
use App\Domains\Accounts\ValueObjects\SocialProvider;

interface SocialIdentityRepository
{
    public function findByProviderUserId(SocialProvider $provider, string $providerUserId): ?SocialIdentity;

    /**
     * @throws SocialIdentityAlreadyLinked
     */
    public function save(SocialIdentity $identity): void;
}
