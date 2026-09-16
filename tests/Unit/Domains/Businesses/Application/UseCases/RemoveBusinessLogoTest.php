<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Presenters\BusinessSettingsPresenter;
use App\Domains\Businesses\Application\UseCases\RemoveBusinessLogo;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\FakeBookingPageSettings;
use Tests\Support\Businesses\FakeBusinessAddressBook;
use Tests\Support\Businesses\FakeBusinessLinkList;
use Tests\Support\Businesses\FakeBusinessLogo;
use Tests\Support\Businesses\FakeBusinessPhoneBook;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\FakeBusinessSchedule;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\Businesses\SettingsFixtures;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->businesses = (new FakeBusinessRepository)->store(
        OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID),
        OnboardingFixtures::business(
            id: SettingsFixtures::OTHER_BUSINESS_ID,
            name: 'Peluquería Ámbar',
            slug: 'peluqueria-ambar',
        ),
    );
    $this->addresses = new FakeBusinessAddressBook;
    $this->links = new FakeBusinessLinkList;
    $this->schedule = new FakeBusinessSchedule;
    $this->bookingPages = new FakeBookingPageSettings;
    $this->phones = new FakeBusinessPhoneBook;
    $this->logo = new FakeBusinessLogo;

    $this->useCaseFor = fn (BusinessContext $context) => new RemoveBusinessLogo(
        $this->logo,
        new BusinessSettingsPresenter(
            $this->businesses,
            $this->addresses,
            $this->links,
            $this->schedule,
            $this->bookingPages,
            $this->phones,
            $this->logo,
        ),
        $context,
    );

    $this->remove = fn () => ($this->useCaseFor)(new FakeBusinessContext)->handle();
});

it('removes the logo and answers with the settings that no longer carry one', function () {
    $this->logo->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::LOGO_URL);

    $response = ($this->remove)();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value()->logoUrl)->toBeNull()
        ->and($this->logo->removals)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('removes the logo of the business in context, never of one a caller could name', function () {
    $this->logo->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::LOGO_URL);
    $this->logo->store(SettingsFixtures::OTHER_BUSINESS_ID, 'https://mizita.test/media/9/other.png');

    ($this->remove)();

    expect($this->logo->removals)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->logo->urlFor(SettingsFixtures::OTHER_BUSINESS_ID))->toBe('https://mizita.test/media/9/other.png');
});

it('takes the business from the context, because a caller names no business of their own', function () {
    expect((new ReflectionMethod(RemoveBusinessLogo::class, 'handle'))->getNumberOfParameters())->toBe(0);
});

it('asks the port to remove a logo the business never had, and answers as it always does', function () {
    $response = ($this->remove)();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value()->logoUrl)->toBeNull()
        ->and($this->logo->removals)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('changes nothing else about the business', function () {
    $this->logo->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::LOGO_URL);

    $data = ($this->remove)()->value();

    expect($data->name)->toBe(OnboardingFixtures::NAME)
        ->and($data->slug)->toBe(OnboardingFixtures::SLUG)
        ->and($this->businesses->saved)->toBe([])
        ->and($this->addresses->wasWritten())->toBeFalse()
        ->and($this->links->replacements)->toBe([])
        ->and($this->schedule->replacements)->toBe([])
        ->and($this->bookingPages->applications)->toBe([]);
});

it('answers with a failure when the media port cannot find the business', function () {
    $this->logo->failingOnRemove(BusinessNotFound::withId(FakeBusinessContext::BUSINESS_ID));

    $response = ($this->remove)();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('business_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->businesses->idsRead)->toBe([]);
});

it('answers with a failure when the business in context is not on record', function () {
    $response = ($this->useCaseFor)(new FakeBusinessContext('01930000-0000-7000-8000-0000000000bf'))->handle();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('business_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
});
