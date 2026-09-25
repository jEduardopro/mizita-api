<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Queue;

use App\Domains\Businesses\Application\Dtos\PurgeClosedBusinessInput;
use App\Domains\Businesses\Application\UseCases\PurgeClosedBusiness;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PurgeClosedBusinessJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $businessId,
    ) {}

    public function handle(PurgeClosedBusiness $purgeClosedBusiness): void
    {
        $purgeClosedBusiness
            ->handle(new PurgeClosedBusinessInput(businessId: $this->businessId))
            ->value();
    }
}
