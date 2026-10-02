<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

use App\Domains\Platform\Entities\PlatformAdmin;
use App\Domains\Platform\Exceptions\PlatformAdminAlreadyExists;
use App\Domains\Platform\ValueObjects\PlatformAdminEmail;

interface PlatformAdminRepository
{
    public function existsByEmail(PlatformAdminEmail $email): bool;

    /**
     * @throws PlatformAdminAlreadyExists
     */
    public function save(PlatformAdmin $admin): void;
}
