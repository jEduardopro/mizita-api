<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

enum SecondFactorProofKind
{
    case Totp;
    case RecoveryCode;
}
