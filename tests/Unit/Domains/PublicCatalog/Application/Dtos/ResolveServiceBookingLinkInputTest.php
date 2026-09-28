<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\ResolveServiceBookingLinkInput;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

describe('a link shaped like one the business shared', function () {
    it('validates a service link on a well formed business slug', function () {
        $input = new ResolveServiceBookingLinkInput(PublicCatalogFixtures::SLUG, PublicCatalogFixtures::SERVICE_SLUG);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a business slug at the maximum length', function () {
        $input = new ResolveServiceBookingLinkInput(str_repeat('a', 60), PublicCatalogFixtures::SERVICE_SLUG);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('leaves the service slug unjudged, because a service the business does not offer falls back to its page', function (string $serviceSlug) {
        $input = new ResolveServiceBookingLinkInput(PublicCatalogFixtures::SLUG, $serviceSlug);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'uppercase' => 'Corte-De-Pelo',
        'an accent' => 'depilación',
        'too long' => str_repeat('a', 61),
    ]);
});

describe('a link no business could have shared', function () {
    it('refuses a malformed business slug as a business that does not exist', function (string $businessSlug) {
        $input = new ResolveServiceBookingLinkInput($businessSlug, PublicCatalogFixtures::SERVICE_SLUG);

        expect(fn () => $input->validate())->toThrow(BusinessPageNotFound::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'uppercase' => 'Ada-Salon',
        'an accent' => 'peluquería',
        'a doubled hyphen' => 'ada--salon',
        'a trailing hyphen' => 'ada-salon-',
        'too long' => str_repeat('a', 61),
    ]);

    it('judges the business even when the service slug is malformed too', function () {
        $input = new ResolveServiceBookingLinkInput('Ada Salon', 'Corte De Pelo');

        expect(fn () => $input->validate())->toThrow(BusinessPageNotFound::class);
    });

    it('refuses with a domain failure, never a PHP error', function () {
        try {
            (new ResolveServiceBookingLinkInput('', PublicCatalogFixtures::SERVICE_SLUG))->validate();
            $refusal = null;
        } catch (Throwable $thrown) {
            $refusal = $thrown;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('business_not_found');
    });
});
