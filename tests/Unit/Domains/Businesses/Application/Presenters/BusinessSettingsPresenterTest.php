<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\Application\Presenters\BusinessSettingsPresenter;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Businesses\ValueObjects\CurrencyCode;
use Tests\Support\Businesses\FakeBookingPageSettings;
use Tests\Support\Businesses\FakeBookingPolicySettings;
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
    $this->bookingPolicies = new FakeBookingPolicySettings;
    $this->phones = new FakeBusinessPhoneBook;
    $this->logo = new FakeBusinessLogo;

    $this->presenter = new BusinessSettingsPresenter(
        $this->businesses,
        $this->addresses,
        $this->links,
        $this->schedule,
        $this->bookingPages,
        $this->bookingPolicies,
        $this->phones,
        $this->logo,
    );

    $this->store = function (...$overrides) {
        $this->businesses->store(OnboardingFixtures::business(...[
            'id' => FakeBusinessContext::BUSINESS_ID,
            ...$overrides,
        ]));
    };

    $this->describe = fn (string $businessId = FakeBusinessContext::BUSINESS_ID) => $this->presenter->describe($businessId);
});

it('assembles the whole settings screen out of the business and its neighbours', function () {
    ($this->store)(
        contactEmail: ContactEmail::fromString(SettingsFixtures::CONTACT_EMAIL),
        about: About::fromString(SettingsFixtures::ABOUT),
        currency: CurrencyCode::fromString('USD'),
    );
    $this->logo->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::LOGO_URL);
    $this->phones->store(FakeBusinessContext::BUSINESS_ID, PhoneNumbers::mexican());
    $this->addresses->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::address());
    $this->schedule->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::entry());
    $this->links->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::link());
    $this->bookingPages->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::bookingPage(
        bannerUrl: SettingsFixtures::BANNER_URL,
    ));
    $this->bookingPolicies->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::bookingPolicy(
        leadTimeMinutes: SettingsFixtures::LEAD_TIME_MINUTES,
    ));

    $data = ($this->describe)();

    expect($data)->toBeInstanceOf(BusinessSettingsData::class)
        ->and($data->id)->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($data->name)->toBe(OnboardingFixtures::NAME)
        ->and($data->slug)->toBe(OnboardingFixtures::SLUG)
        ->and($data->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
        ->and($data->timezone)->toBe(OnboardingFixtures::TIMEZONE)
        ->and($data->about)->toBe(SettingsFixtures::ABOUT)
        ->and($data->contactEmail)->toBe(SettingsFixtures::CONTACT_EMAIL)
        ->and($data->currencyCode)->toBe('USD')
        ->and($data->logoUrl)->toBe(SettingsFixtures::LOGO_URL)
        ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
        ->and($data->address?->street)->toBe(SettingsFixtures::STREET)
        ->and($data->schedule)->toHaveCount(1)
        ->and($data->schedule[0]->weekday)->toBe(1)
        ->and($data->links)->toHaveCount(1)
        ->and($data->links[0]->platform)->toBe('instagram')
        ->and($data->bookingPage->bannerUrl)->toBe(SettingsFixtures::BANNER_URL)
        ->and($data->bookingPolicy->leadTimeMinutes)->toBe(SettingsFixtures::LEAD_TIME_MINUTES);
});

it('always describes a booking policy, because one is provisioned on first use', function () {
    ($this->store)();

    $bookingPolicy = ($this->describe)()->bookingPolicy;

    expect($bookingPolicy)->toBeInstanceOf(BookingPolicySnapshot::class)
        ->and($bookingPolicy->leadTimeMinutes)->toBe(0)
        ->and($bookingPolicy->bookingWindowMinutes)->toBeNull()
        ->and($bookingPolicy->slotGranularityMinutes)->toBe(15)
        ->and($bookingPolicy->cancellationWindowMinutes)->toBe(120)
        ->and($bookingPolicy->policyMessage)->toBeNull()
        ->and($bookingPolicy->displayOnBookingPage)->toBeFalse();
});

it('keeps an unlimited window and a cancellation nobody may use as null', function () {
    ($this->store)();
    $this->bookingPolicies->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::bookingPolicy(
        bookingWindowMinutes: null,
        cancellationWindowMinutes: null,
    ));

    $bookingPolicy = ($this->describe)()->bookingPolicy;

    expect($bookingPolicy->bookingWindowMinutes)->toBeNull()
        ->and($bookingPolicy->cancellationWindowMinutes)->toBeNull();
});

it('describes an address filed with a street alone, city and postal code null', function () {
    ($this->store)();
    $this->addresses->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::address(
        city: null,
        stateId: null,
        postalCode: null,
    ));

    $address = ($this->describe)()->address;

    expect($address?->street)->toBe(SettingsFixtures::STREET)
        ->and($address?->city)->toBeNull()
        ->and($address?->stateId)->toBeNull()
        ->and($address?->postalCode)->toBeNull()
        ->and($address?->countryCode)->toBe('MX');
});

it('describes a business that has filled nothing in beyond onboarding', function () {
    ($this->store)();

    $data = ($this->describe)();

    expect($data->about)->toBeNull()
        ->and($data->contactEmail)->toBeNull()
        ->and($data->logoUrl)->toBeNull()
        ->and($data->phone)->toBeNull()
        ->and($data->address)->toBeNull()
        ->and($data->schedule)->toBe([])
        ->and($data->links)->toBe([])
        ->and($data->currencyCode)->toBe('MXN');
});

it('always describes a booking page, because one is created on first use', function () {
    ($this->store)();

    $bookingPage = ($this->describe)()->bookingPage;

    expect($bookingPage->accentColor)->toBe('ink')
        ->and($bookingPage->buttonShape)->toBe('pill')
        ->and($bookingPage->theme)->toBe('light')
        ->and($bookingPage->bannerUrl)->toBeNull()
        ->and($bookingPage->gallery)->toBe([]);
});

it('hands the business out under its uuid, never a row number', function () {
    ($this->store)();

    expect(($this->describe)()->id)->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and(($this->describe)()->id)->toMatch('/^[0-9a-f-]{36}$/i')
        ->and(($this->describe)()->industryId)->toMatch('/^[0-9a-f-]{36}$/i');
});

it('asks every neighbour about the business it was given and about no other', function () {
    ($this->store)();

    ($this->describe)();

    expect($this->logo->reads)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->phones->reads)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->addresses->reads)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->schedule->reads)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->links->reads)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->bookingPages->reads)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->bookingPolicies->reads)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->businesses->idsRead)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('never reaches another business settings, even when that business has filled everything in', function () {
    ($this->store)();
    $this->businesses->store(OnboardingFixtures::business(
        id: SettingsFixtures::OTHER_BUSINESS_ID,
        name: 'Peluquería Ámbar',
        slug: 'peluqueria-ambar',
    ));
    $this->logo->store(SettingsFixtures::OTHER_BUSINESS_ID, 'https://mizita.test/media/9/other.png');
    $this->phones->store(SettingsFixtures::OTHER_BUSINESS_ID, PhoneNumbers::american());
    $this->addresses->store(SettingsFixtures::OTHER_BUSINESS_ID, SettingsFixtures::address(street: 'Calle Ajena 1'));
    $this->links->store(SettingsFixtures::OTHER_BUSINESS_ID, SettingsFixtures::link(platform: 'facebook'));

    $data = ($this->describe)();

    expect($data->name)->toBe(OnboardingFixtures::NAME)
        ->and($data->logoUrl)->toBeNull()
        ->and($data->phone)->toBeNull()
        ->and($data->address)->toBeNull()
        ->and($data->links)->toBe([]);
});

it('refuses to describe a business that is not on record', function () {
    expect(fn () => ($this->describe)(SettingsFixtures::OTHER_BUSINESS_ID))
        ->toThrow(BusinessNotFound::class);
});

it('asks no neighbour about a business it could not find', function () {
    try {
        ($this->describe)(SettingsFixtures::OTHER_BUSINESS_ID);
    } catch (BusinessNotFound) {
    }

    expect($this->logo->reads)->toBe([])
        ->and($this->phones->reads)->toBe([])
        ->and($this->addresses->reads)->toBe([])
        ->and($this->bookingPolicies->reads)->toBe([]);
});
