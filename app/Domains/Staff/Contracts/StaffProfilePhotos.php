<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Exceptions\StaffProfileNotFound;

interface StaffProfilePhotos
{
    public function urlFor(string $businessId, string $profileId): ?string;

    /**
     * @param  list<string>  $profileIds
     * @return array<string, string>
     */
    public function urlsFor(string $businessId, array $profileIds): array;

    /**
     * @throws StaffProfileNotFound
     */
    public function replace(string $businessId, string $profileId, string $sourcePath, string $fileName): void;

    /**
     * @throws StaffProfileNotFound
     */
    public function remove(string $businessId, string $profileId): void;
}
