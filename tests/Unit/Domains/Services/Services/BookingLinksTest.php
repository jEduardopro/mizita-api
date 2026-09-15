<?php

declare(strict_types=1);

use App\Domains\Services\Services\BookingLinks;

it('builds the public link a customer would follow', function () {
    expect((new BookingLinks('https://mizita.test'))->forService('ada-salon', 'corte-de-pelo'))
        ->toBe('https://mizita.test/b/ada-salon/corte-de-pelo');
});

it('does not double the separator when the base url ends in one', function () {
    expect((new BookingLinks('https://mizita.test/'))->forService('ada-salon', 'corte-de-pelo'))
        ->toBe('https://mizita.test/b/ada-salon/corte-de-pelo');
});

it('keeps a base url that carries a path of its own', function () {
    expect((new BookingLinks('https://mizita.test/app'))->forService('ada-salon', 'corte'))
        ->toBe('https://mizita.test/app/b/ada-salon/corte');
});
