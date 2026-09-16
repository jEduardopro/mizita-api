<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\Application\Presenters\BusinessSettingsPresenter;
use App\Domains\Businesses\Application\UseCases\ShowBusinessSettings;
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
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->businesses = new FakeBusinessRepository;
    $this->addresses = new FakeBusinessAddressBook;
    $this->links = new FakeBusinessLinkList;
    $this->schedule = new FakeBusinessSchedule;
    $this->bookingPages = new FakeBookingPageSettings;
    $this->phones = new FakeBusinessPhoneBook;
    $this->logo = new FakeBusinessLogo;

    $this->useCaseFor = fn (BusinessContext $context) => new ShowBusinessSettings(
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

    $this->show = fn () => ($this->useCaseFor)(new FakeBusinessContext)->handle();
});

it('answers with the settings of the business in context', function () {
    $this->businesses->store(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID));
    $this->logo->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::LOGO_URL);

    $response = ($this->show)();
    $data = $response->value();

    expect($response->succeeded())->toBeTrue()
        ->and($data)->toBeInstanceOf(BusinessSettingsData::class)
        ->and($data->id)->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($data->name)->toBe(OnboardingFixtures::NAME)
        ->and($data->slug)->toBe(OnboardingFixtures::SLUG)
        ->and($data->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
        ->and($data->timezone)->toBe(OnboardingFixtures::TIMEZONE)
        ->and($data->logoUrl)->toBe(SettingsFixtures::LOGO_URL);
});

it('takes the business from the context, because a reader names no business of their own', function () {
    expect((new ReflectionMethod(ShowBusinessSettings::class, 'handle'))->getNumberOfParameters())->toBe(0);
});

it('writes nothing while it reads', function () {
    $this->businesses->store(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID));

    ($this->show)();

    expect($this->businesses->saved)->toBe([])
        ->and($this->addresses->wasWritten())->toBeFalse()
        ->and($this->links->replacements)->toBe([])
        ->and($this->schedule->replacements)->toBe([])
        ->and($this->bookingPages->applications)->toBe([])
        ->and($this->phones->replacements)->toBe([]);
});

it('answers with a failure when the business in context is not on record', function () {
    $response = ($this->show)();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('business_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
});

it('never reads another business settings', function () {
    $this->businesses->store(
        OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID),
        OnboardingFixtures::business(
            id: SettingsFixtures::OTHER_BUSINESS_ID,
            name: 'Peluquería Ámbar',
            slug: 'peluqueria-ambar',
        ),
    );
    $this->phones->store(SettingsFixtures::OTHER_BUSINESS_ID, PhoneNumbers::american());
    $this->links->store(SettingsFixtures::OTHER_BUSINESS_ID, SettingsFixtures::link(platform: 'facebook'));

    $data = ($this->show)()->value();

    expect($data->name)->toBe(OnboardingFixtures::NAME)
        ->and($data->phone)->toBeNull()
        ->and($data->links)->toBe([])
        ->and($this->businesses->idsRead)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('answers about whichever business the context names', function () {
    $this->businesses->store(OnboardingFixtures::business(
        id: SettingsFixtures::OTHER_BUSINESS_ID,
        name: 'Peluquería Ámbar',
        slug: 'peluqueria-ambar',
    ));

    $data = ($this->useCaseFor)(new FakeBusinessContext(SettingsFixtures::OTHER_BUSINESS_ID))->handle()->value();

    expect($data->id)->toBe(SettingsFixtures::OTHER_BUSINESS_ID)
        ->and($data->name)->toBe('Peluquería Ámbar');
});
