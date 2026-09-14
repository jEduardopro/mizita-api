<?php

declare(strict_types=1);

namespace App\Domains\Industries\Contracts;

use App\Domains\Industries\Entities\Industry;
use App\Domains\Industries\Exceptions\IndustryNotFound;

interface IndustryRepository
{
    /**
     * @return list<Industry>
     */
    public function allActive(): array;

    public function existsById(string $id): bool;

    public function isSelectable(string $id): bool;

    /**
     * @throws IndustryNotFound
     */
    public function findById(string $id): Industry;
}
