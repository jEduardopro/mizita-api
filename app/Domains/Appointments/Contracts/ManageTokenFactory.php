<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\ValueObjects\ManageToken;

interface ManageTokenFactory
{
    public function issue(): ManageToken;
}
