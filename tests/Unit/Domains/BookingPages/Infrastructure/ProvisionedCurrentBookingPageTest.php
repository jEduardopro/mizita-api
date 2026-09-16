<?php

declare(strict_types=1);

use App\Domains\BookingPages\Infrastructure\ProvisionedCurrentBookingPage;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->repository = new FakeBookingPageRepository;

    $this->pages = new ProvisionedCurrentBookingPage(
        $this->repository,
        new FixedIdGenerator(BookingPageFixtures::GENERATED_PAGE_ID),
        new FakeClock(BookingPageFixtures::now()),
    );
});

describe('a business that already has a page', function () {
    beforeEach(function () {
        $this->repository->store(BookingPageFixtures::page());
    });

    it('hands back the page on record', function () {
        $page = $this->pages->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($page->id)->toBe(BookingPageFixtures::PAGE_ID)
            ->and($page->accentColor())->toBe(BrandColor::Teal);
    });

    it('saves nothing, because there was nothing to provision', function () {
        $this->pages->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($this->repository->saved)->toBe([]);
    });
});

describe('a business reaching its booking page for the first time', function () {
    it('opens one with the defaults rather than refusing', function () {
        $page = $this->pages->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($page->accentColor())->toBe(BrandColor::Ink)
            ->and($page->buttonShape())->toBe(ButtonShape::Pill)
            ->and($page->theme())->toBe(PageTheme::Light);
    });

    it('gives the new page the uuid the identity generated', function () {
        expect($this->pages->forBusiness(FakeBusinessContext::BUSINESS_ID)->id)
            ->toBe(BookingPageFixtures::GENERATED_PAGE_ID);
    });

    it('stamps it with the instant the clock reported', function () {
        expect($this->pages->forBusiness(FakeBusinessContext::BUSINESS_ID)->createdAt)
            ->toEqual(BookingPageFixtures::now());
    });

    it('scopes it to the business that asked', function () {
        expect($this->pages->forBusiness(BookingPageFixtures::OTHER_BUSINESS_ID)->businessId)
            ->toBe(BookingPageFixtures::OTHER_BUSINESS_ID);
    });

    it('persists it, so the next caller finds the same page', function () {
        $first = $this->pages->forBusiness(FakeBusinessContext::BUSINESS_ID);
        $second = $this->pages->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($this->repository->saved)->toHaveCount(1)
            ->and($second->id)->toBe($first->id);
    });

    it('looks the business up before it opens anything', function () {
        $this->pages->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($this->repository->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});
