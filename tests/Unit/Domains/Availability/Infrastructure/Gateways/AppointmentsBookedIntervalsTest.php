<?php

declare(strict_types=1);

use App\Domains\Availability\Infrastructure\Gateways\AppointmentsBookedIntervals;
use App\Domains\Services\ValueObjects\Buffer;

it('widens the indexed range by at least the longest buffer a service may carry, so no buffered booking hides', function () {
    $widening = (new ReflectionClassConstant(AppointmentsBookedIntervals::class, 'LONGEST_BUFFER_MINUTES'))->getValue();

    expect($widening)->toBeInt()
        ->and($widening)->toBeGreaterThanOrEqual(Buffer::MAXIMUM_MINUTES);
});
