<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\ResolveStaffBookingLinkInput;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

describe('a link shaped like one the business shared', function () {
    it('validates a staff link without a service', function () {
        $input = new ResolveStaffBookingLinkInput(PublicCatalogFixtures::SLUG, PublicCatalogFixtures::STAFF_SLUG, null);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('validates a staff link that names a service', function () {
        $input = new ResolveStaffBookingLinkInput(
            PublicCatalogFixtures::SLUG,
            PublicCatalogFixtures::STAFF_SLUG,
            PublicCatalogFixtures::SERVICE_SLUG,
        );

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('leaves the service slug unjudged, because a service the person does not offer falls back to the staff link', function (string $serviceSlug) {
        $input = new ResolveStaffBookingLinkInput(PublicCatalogFixtures::SLUG, PublicCatalogFixtures::STAFF_SLUG, $serviceSlug);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    })->with([
        'empty' => '',
        'uppercase' => 'Corte-De-Pelo',
        'an accent' => 'depilación',
        'too long' => str_repeat('a', 61),
    ]);
});

describe('a link no business could have shared', function () {
    it('refuses a malformed business slug as a business that does not exist', function (string $businessSlug) {
        $input = new ResolveStaffBookingLinkInput($businessSlug, PublicCatalogFixtures::STAFF_SLUG, null);

        expect(fn () => $input->validate())->toThrow(BusinessPageNotFound::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'uppercase' => 'Ada-Salon',
        'an accent' => 'peluquería',
        'too long' => str_repeat('a', 61),
    ]);

    it('refuses a malformed staff slug as a team member that does not exist', function (string $staffSlug) {
        $input = new ResolveStaffBookingLinkInput(PublicCatalogFixtures::SLUG, $staffSlug, null);

        expect(fn () => $input->validate())->toThrow(StaffBookingPageNotFound::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'uppercase' => 'Jose-Pablo',
        'an accent' => 'josé',
        'a doubled hyphen' => 'jose--pablo',
        'too long' => str_repeat('a', 61),
    ]);

    it('judges the business before the staff member when both are malformed', function () {
        $input = new ResolveStaffBookingLinkInput('Ada Salon', 'Jose Pablo', null);

        expect(fn () => $input->validate())->toThrow(BusinessPageNotFound::class);
    });

    it('refuses with a domain failure, never a PHP error', function (ResolveStaffBookingLinkInput $input) {
        try {
            $input->validate();
            $refusal = null;
        } catch (Throwable $thrown) {
            $refusal = $thrown;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class);
    })->with([
        'a malformed business' => fn () => new ResolveStaffBookingLinkInput('', PublicCatalogFixtures::STAFF_SLUG, null),
        'a malformed staff member' => fn () => new ResolveStaffBookingLinkInput(PublicCatalogFixtures::SLUG, '', null),
    ]);
});
