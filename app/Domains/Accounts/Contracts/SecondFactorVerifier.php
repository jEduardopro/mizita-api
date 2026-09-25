<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Exceptions\InvalidRecoveryCode;
use App\Domains\Accounts\Exceptions\InvalidTwoFactorCode;
use App\Domains\Accounts\ValueObjects\SecondFactorProof;

interface SecondFactorVerifier
{
    /**
     * @throws InvalidTwoFactorCode
     * @throws InvalidRecoveryCode
     */
    public function verify(string $accountId, SecondFactorProof $proof): void;
}
