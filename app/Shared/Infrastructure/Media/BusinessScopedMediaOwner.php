<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Media;

interface BusinessScopedMediaOwner
{
    public function businessKey(): int;
}
