<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\ValueObjects\TwoFactorStatus;

final readonly class SignInSecurityData
{
    /**
     * @param  list<PasskeyData>  $passkeys
     */
    public function __construct(
        public bool $hasPassword,
        public TwoFactorStatus $twoFactor,
        public array $passkeys,
    ) {}
}
