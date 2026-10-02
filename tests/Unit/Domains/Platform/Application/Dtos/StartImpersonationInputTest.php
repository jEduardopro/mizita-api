<?php

declare(strict_types=1);

use App\Domains\Platform\Application\Dtos\StartImpersonationInput;
use App\Domains\Platform\Exceptions\ImpersonatedBusinessNotFound;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Platform\ImpersonationFixtures;

it('accepts a business uuid', function (string $businessId) {
    $input = new StartImpersonationInput(ImpersonationFixtures::ADMIN_ID, $businessId);

    expect(fn () => $input->validate())->not->toThrow(Throwable::class);
})->with([
    'lowercase' => [ImpersonationFixtures::BUSINESS_ID],
    'uppercase' => [strtoupper(ImpersonationFixtures::BUSINESS_ID)],
]);

it('reads a business id that is no uuid as a business that does not exist', function (string $businessId) {
    $input = new StartImpersonationInput(ImpersonationFixtures::ADMIN_ID, $businessId);

    expect(fn () => $input->validate())->toThrow(ImpersonatedBusinessNotFound::class);
})->with([
    'empty' => [''],
    'an int primary key' => ['42'],
    'a slug' => ['barberia-centro'],
    'a uuid with a trailing newline' => [ImpersonationFixtures::BUSINESS_ID."\n"],
    'a uuid with surrounding spaces' => [' '.ImpersonationFixtures::BUSINESS_ID.' '],
    'a uuid without dashes' => [str_replace('-', '', ImpersonationFixtures::BUSINESS_ID)],
    'a uuid one character short' => [substr(ImpersonationFixtures::BUSINESS_ID, 0, -1)],
    'a uuid with a non hex character' => ['01930000-0000-7000-8000-0000000ab00g'],
]);

it('refuses with a domain failure, so the edge renders a 404 rather than a 500', function () {
    try {
        (new StartImpersonationInput(ImpersonationFixtures::ADMIN_ID, '42'))->validate();
    } catch (ImpersonatedBusinessNotFound $failure) {
        expect($failure)->toBeInstanceOf(DomainFailure::class);

        return;
    }

    test()->fail('A non-uuid business id was accepted.');
});
