<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\RevealedTemporaryPassword;
use App\Domains\Staff\Infrastructure\Http\Resources\TemporaryPasswordResource;
use Tests\Support\Staff\StaffFixtures;
use Tests\TestCase;

uses(TestCase::class);

it('serializes only the temporary password, wrapped in data', function () {
    $body = TemporaryPasswordResource::make(new RevealedTemporaryPassword(StaffFixtures::TEMPORARY_PASSWORD))
        ->response()
        ->getData(true);

    expect($body)->toBe(['data' => ['temporary_password' => StaffFixtures::TEMPORARY_PASSWORD]]);
});
