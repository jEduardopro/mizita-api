<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\DuplicateServiceInput;
use App\Domains\Services\Exceptions\InvalidServiceName;
use App\Domains\Services\Exceptions\ServiceNotFound;
use Tests\Support\Services\ServiceFixtures;

it('takes the name the client offered', function () {
    $input = DuplicateServiceInput::fromRequest(['name' => 'Corte de pelo (Copia)'], ServiceFixtures::SERVICE_ID);

    expect($input->serviceId)->toBe(ServiceFixtures::SERVICE_ID)
        ->and($input->name)->toBe('Corte de pelo (Copia)');
});

it('carries no name when the client offered none it can use', function (array $payload) {
    expect(DuplicateServiceInput::fromRequest($payload, ServiceFixtures::SERVICE_ID)->name)->toBeNull();
})->with([
    'missing' => [[]],
    'null' => [['name' => null]],
    'empty' => [['name' => '']],
    'spaces' => [['name' => '   ']],
    'an array' => [['name' => ['Corte']]],
    'a number' => [['name' => 42]],
]);

it('accepts a duplicate with no name, because the use case names it', function () {
    expect(fn () => DuplicateServiceInput::fromRequest([], ServiceFixtures::SERVICE_ID)->validate())
        ->not->toThrow(Throwable::class);
});

it('accepts a duplicate with a name the copy may carry', function () {
    expect(fn () => DuplicateServiceInput::fromRequest(
        ['name' => 'Corte de pelo (Copia)'],
        ServiceFixtures::SERVICE_ID,
    )->validate())->not->toThrow(Throwable::class);
});

it('refuses an identifier that cannot be a service', function (string $serviceId) {
    expect(fn () => DuplicateServiceInput::fromRequest([], $serviceId)->validate())
        ->toThrow(ServiceNotFound::class);
})->with([
    'empty' => '',
    'not a uuid' => 'corte-de-pelo',
    'a truncated uuid' => '01930000-0000-7000-8000',
]);

it('refuses a name the copy could not carry', function (string $name, string $exception) {
    expect(fn () => DuplicateServiceInput::fromRequest(['name' => $name], ServiceFixtures::SERVICE_ID)->validate())
        ->toThrow($exception);
})->with([
    'one character' => ['A', InvalidServiceName::class],
    'too long' => [str_repeat('a', 121), InvalidServiceName::class],
]);

it('checks the identifier before the name', function () {
    expect(fn () => DuplicateServiceInput::fromRequest(['name' => 'A'], 'not-a-uuid')->validate())
        ->toThrow(ServiceNotFound::class);
});
