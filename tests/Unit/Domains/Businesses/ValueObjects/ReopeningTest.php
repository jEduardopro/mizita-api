<?php

declare(strict_types=1);

use App\Domains\Businesses\ValueObjects\Reopening;

it('asks for no reprovisioning when the data survived the closure', function () {
    expect(Reopening::withDataIntact()->requiresReprovisioning())->toBeFalse();
});

it('asks for reprovisioning when the purge erased the data', function () {
    expect(Reopening::afterPurge()->requiresReprovisioning())->toBeTrue();
});
