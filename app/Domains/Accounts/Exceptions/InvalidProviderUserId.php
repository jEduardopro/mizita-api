<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use DomainException;

/**
 * An empty provider subject is never a valid identity: it would match every
 * other empty subject already stored, handing one caller somebody else's
 * account. The guard lives on the entity so it holds on every path, not only
 * the ones that happen to build a GoogleIdentity first.
 */
final class InvalidProviderUserId extends DomainException
{
    public static function empty(): self
    {
        return new self('A provider user id cannot be empty.');
    }
}
