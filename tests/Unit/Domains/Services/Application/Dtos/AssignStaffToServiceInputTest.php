<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\AssignStaffToServiceInput;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Services\ServiceFixtures;

it('accepts identifiers a service and a staff member could carry', function (string $serviceId, string $staffMemberId) {
    expect(fn () => (new AssignStaffToServiceInput($serviceId, $staffMemberId))->validate())
        ->not->toThrow(Throwable::class);
})->with([
    'two uuids' => [ServiceFixtures::SERVICE_ID, ServiceFixtures::STAFF_ID],
    'two uuids in uppercase' => ['01930000-0000-7000-8000-0000000000E1', '01930000-0000-7000-8000-0000000000D1'],
]);

it('refuses a service identifier that cannot be a service, as not found', function (string $serviceId) {
    expect(fn () => (new AssignStaffToServiceInput($serviceId, ServiceFixtures::STAFF_ID))->validate())
        ->toThrow(ServiceNotFound::class);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'a slug' => 'corte-de-pelo',
    'an integer key' => '42',
    'a truncated uuid' => '01930000-0000-7000-8000',
    'a uuid with a trailing newline' => ServiceFixtures::SERVICE_ID."\n",
    'sql' => "' or 1=1 --",
]);

it('refuses a staff identifier that cannot be a staff member, as unknown', function (string $staffMemberId) {
    expect(fn () => (new AssignStaffToServiceInput(ServiceFixtures::SERVICE_ID, $staffMemberId))->validate())
        ->toThrow(UnknownStaffMember::class);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'an integer key' => '7',
    'a truncated uuid' => '01930000-0000-7000-8000',
    'a uuid with a trailing newline' => ServiceFixtures::STAFF_ID."\n",
    'sql' => "' or 1=1 --",
]);

it('reports the service before the staff member when neither identifier is usable', function () {
    expect(fn () => (new AssignStaffToServiceInput('not-a-uuid', 'not-a-uuid'))->validate())
        ->toThrow(ServiceNotFound::class);
});

it('refuses with a failure the use case can return instead of throw', function (string $serviceId, string $staffMemberId) {
    $refusal = null;

    try {
        (new AssignStaffToServiceInput($serviceId, $staffMemberId))->validate();
    } catch (Throwable $thrown) {
        $refusal = $thrown;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class);
})->with([
    'a bad service' => ['not-a-uuid', ServiceFixtures::STAFF_ID],
    'a bad staff member' => [ServiceFixtures::SERVICE_ID, 'not-a-uuid'],
]);
