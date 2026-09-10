<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

/**
 * Input boundary for CreateBusiness. Framework free: the controller maps
 * the HTTP request into this object.
 */
final readonly class CreateBusinessInput
{
    public function __construct(
        public string $name,
        public string $slug,
    ) {}
}
