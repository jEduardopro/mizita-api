<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure;

use App\Domains\Appointments\Contracts\ManageTokenFactory;
use App\Domains\Appointments\ValueObjects\ManageToken;

final class RandomManageTokenFactory implements ManageTokenFactory
{
    public function issue(): ManageToken
    {
        return ManageToken::fromString(bin2hex(random_bytes(ManageToken::BYTE_LENGTH)));
    }
}
