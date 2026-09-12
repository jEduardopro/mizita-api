<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\ValueObjects\GoogleIdentity;

/**
 * Port for turning a Google ID token into the identity it asserts.
 *
 * Implementations are responsible for the whole check - signature, issuer,
 * audience and expiry - so a caller holding the returned value object can
 * treat it as proven.
 */
interface GoogleIdentityVerifier
{
    /**
     * @throws InvalidGoogleIdToken
     */
    public function verify(string $idToken): GoogleIdentity;
}
