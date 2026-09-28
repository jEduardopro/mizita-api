<?php

declare(strict_types=1);

use App\Domains\Staff\Services\BookingLinks;
use App\Domains\Staff\ValueObjects\BookingSlug;

it('builds the public link of a team member under the team segment of the business page', function () {
    expect((new BookingLinks('https://mizita.test'))->forStaffMember('barberia-nunoa', BookingSlug::restore('jose-pablo')))
        ->toBe('https://mizita.test/barberia-nunoa/equipo/jose-pablo');
});

it('does not double the separator when the base url ends in one', function (string $baseUrl) {
    expect((new BookingLinks($baseUrl))->forStaffMember('barberia-nunoa', BookingSlug::restore('jose-pablo')))
        ->toBe('https://mizita.test/barberia-nunoa/equipo/jose-pablo');
})->with([
    'one trailing slash' => 'https://mizita.test/',
    'several trailing slashes' => 'https://mizita.test///',
]);

it('keeps a base url that carries a path of its own', function () {
    expect((new BookingLinks('https://mizita.test/app'))->forStaffMember('barberia-nunoa', BookingSlug::restore('ada')))
        ->toBe('https://mizita.test/app/barberia-nunoa/equipo/ada');
});
