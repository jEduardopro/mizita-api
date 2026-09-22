<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use App\Shared\ValueObjects\Pagination;

final readonly class CustomerAppointmentQuery
{
    public function __construct(
        public string $customerId,
        public Pagination $pagination,
    ) {}
}
