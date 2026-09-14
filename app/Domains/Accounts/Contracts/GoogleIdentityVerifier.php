<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\ValueObjects\GoogleIdentity;

interface GoogleIdentityVerifier
{
    /**
     * @throws InvalidGoogleIdToken
     */
    public function verify(string $idToken): GoogleIdentity;
}
