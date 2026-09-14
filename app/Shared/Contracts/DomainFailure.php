<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Shared\ValueObjects\DomainFailureKind;

interface DomainFailure
{
    public function errorCode(): string;

    public function kind(): DomainFailureKind;
}
