<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\ValueObjects\ReferenceCode;

interface ReferenceCodeGenerator
{
    public function next(): ReferenceCode;
}
