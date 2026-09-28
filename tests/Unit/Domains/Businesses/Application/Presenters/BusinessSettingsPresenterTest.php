<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\Application\Presenters\BusinessSettingsPresenter;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Businesses\ValueObjects\ContactFieldPreference;
use App\Shared\ValueObjects\CurrencyCode;
use Tests\Support\Businesses\FakeBookingPageSettings;
use Tests\Support\Businesses\FakeBookingPolicySettings;
use Tests\Support\Businesses\FakeBookingRulesAllowance;
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
    $this->bookingRules = FakeBookingRulesAllowance::onCompletePlan();
    $this->phones = new FakeBusinessPhoneBook;
    $this->logo = new FakeBusinessLogo;

    $this->presenter = fn () => new BusinessSettingsPresenter(
        $this->businesses,
        $this->addresses,
        $this->links,
        $this->schedule,
        $this->bookingPages,
        $this->bookingPolicies,
        $this->bookingRules,
        $this->phones,
        $this->logo,
    );

    $this->store = function (...$overrides) {
        $this->businesses->store(OnboardingFixtures::business(...[
            'id' => FakeBusinessContext::BUSINESS_ID,
            ...$overrides,
        ]));
    };

    $this->describe = fn (string $businessId = FakeBusinessContext::BUSINESS_ID) => ($this->presenter)()->describe($businessId);
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
    $this->bookingPolicies->storeContactFields(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::contactFields(
        phone: ContactFieldPreference::Hidden,
        email: ContactFieldPreference::Required,
        address: ContactFieldPreference::Optional,
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
        ->and($data->bookingPolicy->leadTimeMinutes)->toBe(SettingsFixtures::LEAD_TIME_MINUTES)
        ->and($data->contactFields->phone)->toBe(ContactFieldPreference::Hidden)
        ->and($data->contactFields->email)->toBe(ContactFieldPreference::Required)
        ->and($data->contactFields->address)->toBe(ContactFieldPreference::Optional);
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
        ->and($this->bookingPolicies->contactFieldReads)->toBe([FakeBusinessContext::BUSINESS_ID])
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
    $this->bookingPolicies->storeContactFields(SettingsFixtures::OTHER_BUSINESS_ID, SettingsFixtures::contactFields(
        phone: ContactFieldPreference::Hidden,
        address: ContactFieldPreference::Required,
    ));

    $data = ($this->describe)();

    expect($data->name)->toBe(OnboardingFixtures::NAME)
        ->and($data->logoUrl)->toBeNull()
        ->and($data->phone)->toBeNull()
        ->and($data->address)->toBeNull()
        ->and($data->links)->toBe([])
        ->and($data->contactFields->phone)->toBe(ContactFieldPreference::Required)
        ->and($data->contactFields->address)->toBe(ContactFieldPreference::Hidden);
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
        ->and($this->bookingPolicies->reads)->toBe([])
        ->and($this->bookingPolicies->contactFieldReads)->toBe([])
        ->and($this->bookingRules->consultations)->toBe([]);
});

describe('the booking preferences the plan allows', function () {
    beforeEach(function () {
        ($this->store)();

        $this->bookingPolicies
            ->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::bookingPolicy(
                leadTimeMinutes: SettingsFixtures::LEAD_TIME_MINUTES,
                bookingWindowMinutes: SettingsFixtures::BOOKING_WINDOW_MINUTES,
                slotGranularityMinutes: SettingsFixtures::SLOT_GRANULARITY_MINUTES,
                cancellationWindowMinutes: SettingsFixtures::CANCELLATION_WINDOW_MINUTES,
                policyMessage: SettingsFixtures::POLICY_MESSAGE,
                displayOnBookingPage: true,
            ))
            ->storeContactFields(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::contactFields(
                phone: ContactFieldPreference::Hidden,
                email: ContactFieldPreference::Required,
                address: ContactFieldPreference::Optional,
            ))
            ->withPlatformDefaults(
                SettingsFixtures::bookingPolicy(slotGranularityMinutes: 15, cancellationWindowMinutes: 120),
                SettingsFixtures::contactFields(
                    phone: ContactFieldPreference::Required,
                    email: ContactFieldPreference::Optional,
                    address: ContactFieldPreference::Hidden,
                ),
            );
    });

    describe('on the free plan', function () {
        beforeEach(function () {
            $this->bookingRules = FakeBookingRulesAllowance::onFreePlan();
        });

        it('describes the platform default policy in place of the one the business stored', function () {
            $bookingPolicy = ($this->describe)()->bookingPolicy;

            expect($bookingPolicy->leadTimeMinutes)->toBe(0)
                ->and($bookingPolicy->bookingWindowMinutes)->toBeNull()
                ->and($bookingPolicy->slotGranularityMinutes)->toBe(15)
                ->and($bookingPolicy->cancellationWindowMinutes)->toBe(120)
                ->and($bookingPolicy->policyMessage)->toBeNull()
                ->and($bookingPolicy->displayOnBookingPage)->toBeFalse();
        });

        it('describes the platform default contact fields in place of the ones the business stored', function () {
            $contactFields = ($this->describe)()->contactFields;

            expect($contactFields->phone)->toBe(ContactFieldPreference::Required)
                ->and($contactFields->email)->toBe(ContactFieldPreference::Optional)
                ->and($contactFields->address)->toBe(ContactFieldPreference::Hidden);
        });

        it('never reads the stored policy or contact fields at all', function () {
            ($this->describe)();

            expect($this->bookingPolicies->reads)->toBe([])
                ->and($this->bookingPolicies->contactFieldReads)->toBe([])
                ->and($this->bookingPolicies->platformDefaultReads)->toBe(2);
        });

        it('still describes everything else the business filled in', function () {
            $this->logo->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::LOGO_URL);
            $this->links->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::link());

            $data = ($this->describe)();

            expect($data->id)->toBe(FakeBusinessContext::BUSINESS_ID)
                ->and($data->name)->toBe(OnboardingFixtures::NAME)
                ->and($data->logoUrl)->toBe(SettingsFixtures::LOGO_URL)
                ->and($data->links)->toHaveCount(1);
        });

        it('asks the allowance about the business it was given, once', function () {
            ($this->describe)();

            expect($this->bookingRules->consultations)->toBe([FakeBusinessContext::BUSINESS_ID]);
        });
    });

    describe('on the complete plan', function () {
        it('describes the policy the business stored, not the platform default', function () {
            $bookingPolicy = ($this->describe)()->bookingPolicy;

            expect($bookingPolicy->leadTimeMinutes)->toBe(SettingsFixtures::LEAD_TIME_MINUTES)
                ->and($bookingPolicy->bookingWindowMinutes)->toBe(SettingsFixtures::BOOKING_WINDOW_MINUTES)
                ->and($bookingPolicy->slotGranularityMinutes)->toBe(SettingsFixtures::SLOT_GRANULARITY_MINUTES)
                ->and($bookingPolicy->cancellationWindowMinutes)->toBe(SettingsFixtures::CANCELLATION_WINDOW_MINUTES)
                ->and($bookingPolicy->policyMessage)->toBe(SettingsFixtures::POLICY_MESSAGE)
                ->and($bookingPolicy->displayOnBookingPage)->toBeTrue();
        });

        it('describes the contact fields the business stored, not the platform default', function () {
            $contactFields = ($this->describe)()->contactFields;

            expect($contactFields->phone)->toBe(ContactFieldPreference::Hidden)
                ->and($contactFields->email)->toBe(ContactFieldPreference::Required)
                ->and($contactFields->address)->toBe(ContactFieldPreference::Optional);
        });

        it('never asks for the platform defaults', function () {
            ($this->describe)();

            expect($this->bookingPolicies->platformDefaultReads)->toBe(0)
                ->and($this->bookingRules->consultations)->toBe([FakeBusinessContext::BUSINESS_ID]);
        });
    });
});
