<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\DeleteServiceInput;
use App\Domains\Services\Exceptions\ServiceNotFound;
use Tests\Support\Services\ServiceFixtures;

it('accepts an identifier a service could carry', function (string $serviceId) {
    expect(fn () => (new DeleteServiceInput($serviceId))->validate())->not->toThrow(Throwable::class);
})->with([
    'a uuid' => ServiceFixtures::SERVICE_ID,
    'a uuid in uppercase' => '01930000-0000-7000-8000-0000000000E1',
]);

it('refuses an identifier that cannot be a service, as not found rather than invalid', function (string $serviceId) {
    expect(fn () => (new DeleteServiceInput($serviceId))->validate())
        ->toThrow(ServiceNotFound::class);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'not a uuid' => 'corte-de-pelo',
    'an integer key' => '42',
    'a truncated uuid' => '01930000-0000-7000-8000',
    'a uuid with a trailing newline' => ServiceFixtures::SERVICE_ID."\n",
    'sql' => "' or 1=1 --",
]);
