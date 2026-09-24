<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Exceptions\StaffProfileNotFound;

interface StaffProfilePhotos
{
    public function urlFor(string $businessId, string $profileId): ?string;

    /**
     * @throws StaffProfileNotFound
     */
    public function replace(string $businessId, string $profileId, string $sourcePath, string $fileName): void;

    /**
     * @throws StaffProfileNotFound
     */
    public function remove(string $businessId, string $profileId): void;
}
