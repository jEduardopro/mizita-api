<?php

declare(strict_types=1);

use App\Domains\Platform\ValueObjects\Plan;

it('names exactly the two plans the business list shows', function () {
    expect(array_map(fn (Plan $plan): string => $plan->value, Plan::cases()))->toBe(['free', 'complete']);
});
