<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\AddressCityCannotBeCleared;
use App\Domains\Addresses\Exceptions\AddressPostalCodeCannotBeCleared;
use App\Domains\Addresses\Exceptions\InvalidAddressStreet;
use App\Domains\Availability\Exceptions\OverlappingScheduleIntervals;
use App\Domains\BookingPolicies\Exceptions\BookingPolicyNotFound;
use App\Domains\BookingPolicies\Exceptions\InvalidLeadTime;
use App\Domains\Businesses\Application\Dtos\BookingPolicyInput;
use App\Domains\Businesses\Application\Dtos\ContactFieldsInput;
use App\Domains\Businesses\Application\Dtos\ContactInput;
use App\Domains\Businesses\Application\Dtos\LinksInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Application\Dtos\ScheduleInput;
use App\Domains\Businesses\Application\Dtos\UpdateBusinessSettingsInput;
use App\Domains\Businesses\Application\Presenters\BusinessSettingsPresenter;
use App\Domains\Businesses\Application\UseCases\UpdateBusinessSettings;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\ValueObjects\BookingPolicyPreferences;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;
use App\Domains\Businesses\ValueObjects\ContactFieldPreference;
use App\Domains\Businesses\ValueObjects\ContactFieldPreferences;
use App\Domains\Links\Exceptions\InvalidLinkPlatform;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\FakeBookingPageSettings;
use Tests\Support\Businesses\FakeBookingPolicySettings;
use Tests\Support\Businesses\FakeBookingRulesAllowance;
use Tests\Support\Businesses\FakeBusinessAddressBook;
use Tests\Support\Businesses\FakeBusinessLinkList;
use Tests\Support\Businesses\FakeBusinessLogo;
use Tests\Support\Businesses\FakeBusinessPhoneBook;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\FakeBusinessSchedule;
use Tests\Support\Businesses\FakeIndustryCatalog;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\Businesses\SettingsFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakePhoneNumberParser;
use Tests\Support\FakeTransactionManager;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->businesses = (new FakeBusinessRepository)
        ->store(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID));
    $this->industries = FakeIndustryCatalog::holding(
        OnboardingFixtures::INDUSTRY_ID,
        SettingsFixtures::OTHER_INDUSTRY_ID,
    );
    $this->addresses = new FakeBusinessAddressBook;
    $this->links = new FakeBusinessLinkList;
    $this->schedule = new FakeBusinessSchedule;
    $this->bookingPages = new FakeBookingPageSettings;
    $this->bookingPolicies = new FakeBookingPolicySettings;
    $this->bookingRules = FakeBookingRulesAllowance::onCompletePlan();
    $this->phones = new FakeBusinessPhoneBook;
    $this->logo = new FakeBusinessLogo;
    $this->parser = FakePhoneNumberParser::accepting(PhoneNumbers::mexican(), PhoneNumbers::american());
    $this->transactions = new FakeTransactionManager;

    $this->presenterRules = FakeBookingRulesAllowance::onCompletePlan();

    $this->onFreePlan = function () {
        $this->bookingRules = FakeBookingRulesAllowance::onFreePlan();
        $this->presenterRules = FakeBookingRulesAllowance::onFreePlan();
    };

    $this->presenterOver = fn (FakeBusinessRepository $businesses) => new BusinessSettingsPresenter(
        $businesses,
        $this->addresses,
        $this->links,
        $this->schedule,
        $this->bookingPages,
        $this->bookingPolicies,
        $this->presenterRules,
        $this->phones,
        $this->logo,
    );

    $this->useCaseWith = fn (TransactionManager $transactions) => new UpdateBusinessSettings(
        $this->businesses,
        $this->industries,
        $this->addresses,
        $this->links,
        $this->schedule,
        $this->bookingPages,
        $this->bookingPolicies,
        $this->bookingRules,
        $this->phones,
        ($this->presenterOver)($this->businesses),
        $this->parser,
        new FakeBusinessContext,
        $transactions,
    );

    $this->update = fn (UpdateBusinessSettingsInput $input) => ($this->useCaseWith)($this->transactions)->handle($input);

    $this->expectNothingWritten = fn () => expect($this->businesses->saved)->toBe([])
        ->and($this->phones->replacements)->toBe([])
        ->and($this->addresses->wasWritten())->toBeFalse()
        ->and($this->bookingPages->applications)->toBe([])
        ->and($this->bookingPolicies->applications)->toBe([])
        ->and($this->bookingPolicies->contactFieldApplications)->toBe([])
        ->and($this->schedule->replacements)->toBe([])
        ->and($this->links->replacements)->toBe([]);
});

describe('updating every section at once', function () {
    it('writes each section through the port that owns it and answers with the settings that came back', function () {
        $data = ($this->update)(SettingsFixtures::everything())->value();

        expect($data->id)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($data->about)->toBe(SettingsFixtures::ABOUT)
            ->and($data->contactEmail)->toBe(SettingsFixtures::CONTACT_EMAIL)
            ->and($data->currencyCode)->toBe('MXN')
            ->and($data->timezone)->toBe(OnboardingFixtures::TIMEZONE)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->address?->street)->toBe(SettingsFixtures::STREET)
            ->and($data->schedule)->toHaveCount(1)
            ->and($data->links)->toHaveCount(1)
            ->and($data->bookingPage->accentColor)->toBe('teal')
            ->and($data->bookingPolicy->leadTimeMinutes)->toBe(SettingsFixtures::LEAD_TIME_MINUTES)
            ->and($data->contactFields->phone)->toBe(ContactFieldPreference::Hidden)
            ->and($data->contactFields->email)->toBe(ContactFieldPreference::Required)
            ->and($data->contactFields->address)->toBe(ContactFieldPreference::Optional);
    });

    it('hands the whole update to the transaction manager as one unit of work', function () {
        ($this->update)(SettingsFixtures::everything());

        expect($this->transactions->runs())->toBe(1);
    });

    it('saves the business once, however many of its own fields changed', function () {
        ($this->update)(SettingsFixtures::everything());

        expect($this->businesses->saved)->toHaveCount(1)
            ->and($this->businesses->saved[0]->id)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('answers with the settings as they now stand, not as they were submitted', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput(
            contact: SettingsFixtures::contact(contactEmail: '  Hola@BARBERIA.COM  '),
        ))->value();

        expect($data->contactEmail)->toBe('Hola@barberia.com');
    });
});

describe('the brand', function () {
    it('renames the business after asking whether the name is free', function () {
        ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(name: 'Barbería del Centro')));

        expect($this->businesses->nameChecks)->toBe(['Barbería del Centro'])
            ->and($this->businesses->saved[0]->name())->toBe('Barbería del Centro');
    });

    it('trims the name before it asks about it and before it stores it', function () {
        ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(name: '  Barbería del Centro  ')));

        expect($this->businesses->nameChecks)->toBe(['Barbería del Centro'])
            ->and($this->businesses->saved[0]->name())->toBe('Barbería del Centro');
    });

    it('asks nobody about a name that did not change, in whatever case it was resubmitted', function (string $name) {
        ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(name: $name)));

        expect($this->businesses->nameChecks)->toBe([])
            ->and($this->businesses->saved[0]->name())->toBe(OnboardingFixtures::NAME);
    })->with([
        'the same name' => OnboardingFixtures::NAME,
        'the same name uppercased' => 'BARBERÍA ÑANDÚ',
        'the same name padded' => '  Barbería Ñandú  ',
    ]);

    it('refuses a name another business already carries', function () {
        $this->businesses->withTakenNames('Peluquería Ámbar');

        $response = ($this->update)(new UpdateBusinessSettingsInput(
            brand: SettingsFixtures::brand(name: 'Peluquería Ámbar'),
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_name_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);

        ($this->expectNothingWritten)();
    });

    it('never touches the slug when only the name changed, because the booking link is already shared', function () {
        ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(name: 'Barbería del Centro')));

        expect($this->businesses->saved[0]->slug())->toBe(OnboardingFixtures::SLUG)
            ->and($this->businesses->slugChecks)->toBe([]);
    });

    it('moves the booking page to the new address when the slug itself changed', function () {
        ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(slug: 'barberia-del-centro')));

        expect($this->businesses->slugChecks)->toBe(['barberia-del-centro'])
            ->and($this->businesses->saved[0]->slug())->toBe('barberia-del-centro');
    });

    it('refuses a slug another business already answers at', function () {
        $this->businesses->withTakenSlugs('peluqueria-ambar');

        $response = ($this->update)(new UpdateBusinessSettingsInput(
            brand: SettingsFixtures::brand(slug: 'peluqueria-ambar'),
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_slug_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);

        ($this->expectNothingWritten)();
    });

    it('reclassifies the business once the catalog recognises the industry', function () {
        ($this->update)(new UpdateBusinessSettingsInput(
            brand: SettingsFixtures::brand(industryId: SettingsFixtures::OTHER_INDUSTRY_ID),
        ));

        expect($this->industries->lookups)->toBe([SettingsFixtures::OTHER_INDUSTRY_ID])
            ->and($this->businesses->saved[0]->industryId())->toBe(SettingsFixtures::OTHER_INDUSTRY_ID)
            ->and($this->businesses->saved[0]->industryId())->toMatch('/^[0-9a-f-]{36}$/i');
    });

    it('asks the catalog nothing about an industry that did not change', function () {
        ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand()));

        expect($this->industries->wasConsulted())->toBeFalse();
    });

    it('refuses an industry the catalog does not hold', function () {
        $response = ($this->update)(new UpdateBusinessSettingsInput(
            brand: SettingsFixtures::brand(industryId: '01930000-0000-7000-8000-0000000000ff'),
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unknown_industry')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);

        ($this->expectNothingWritten)();
    });

    it('clears the description when the business no longer says anything about itself', function () {
        ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(about: SettingsFixtures::ABOUT)));

        $data = ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(about: null)))->value();

        expect($data->about)->toBeNull();
    });
});

describe('the contact details', function () {
    it('stores the address as the value object normalised it', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput(
            contact: SettingsFixtures::contact(contactEmail: 'Hola@BARBERIA.com'),
        ))->value();

        expect($data->contactEmail)->toBe('Hola@barberia.com');
    });

    it('replaces the phone with the number the parser made sense of', function () {
        ($this->update)(new UpdateBusinessSettingsInput(
            contact: SettingsFixtures::contact(phone: SettingsFixtures::submittedPhone()),
        ));

        expect($this->phones->replacements)->toHaveCount(1)
            ->and($this->phones->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->phones->replacements[0]['phone']?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($this->parser->calls()[0]['country'])->toBe(CountryCode::Mx);
    });

    it('clears both the address and the phone when the business publishes neither', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput(
            contact: SettingsFixtures::contact(contactEmail: null, phone: null),
        ))->value();

        expect($data->contactEmail)->toBeNull()
            ->and($data->phone)->toBeNull()
            ->and($this->phones->replacements[0]['phone'])->toBeNull()
            ->and($this->parser->wasConsulted())->toBeFalse();
    });

    it('refuses a country the platform does not dial', function () {
        $response = ($this->update)(new UpdateBusinessSettingsInput(
            contact: SettingsFixtures::contact(phone: SettingsFixtures::submittedPhone()),
        ));

        expect($response->succeeded())->toBeTrue();

        $refused = ($this->update)(new UpdateBusinessSettingsInput(
            contact: new ContactInput(
                SettingsFixtures::CONTACT_EMAIL,
                new PhoneNumberInput('FR', '612345678'),
            ),
        ));

        expect($refused->failed())->toBeTrue()
            ->and($refused->error()->code)->toBe('unsupported_phone_number')
            ->and($refused->error()->kind)->toBe(DomainFailureKind::Invalid);
    });

    it('refuses a number the parser cannot read, without writing anything', function () {
        $response = ($this->update)(new UpdateBusinessSettingsInput(
            contact: SettingsFixtures::contact(phone: SettingsFixtures::submittedPhone(nationalNumber: '9999999999')),
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unsupported_phone_number');

        ($this->expectNothingWritten)();
    });
});

describe('the location', function () {
    it('takes the timezone and the currency the business keeps its books in', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput(
            location: SettingsFixtures::location(currencyCode: 'usd', timezone: 'America/Mexico_City'),
        ))->value();

        expect($data->timezone)->toBe('America/Mexico_City')
            ->and($data->currencyCode)->toBe('USD');
    });

    it('replaces the address with the place, trimmed', function () {
        ($this->update)(new UpdateBusinessSettingsInput(location: SettingsFixtures::location(
            street: '  Avenida Insurgentes Sur 1602  ',
            city: '  Ciudad de México  ',
            postalCode: '  03940  ',
            countryCode: '  MX  ',
        )));

        expect($this->addresses->replacements)->toHaveCount(1)
            ->and($this->addresses->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->addresses->replacements[0]['address']->street)->toBe(SettingsFixtures::STREET)
            ->and($this->addresses->replacements[0]['address']->city)->toBe(SettingsFixtures::CITY)
            ->and($this->addresses->replacements[0]['address']->postalCode)->toBe(SettingsFixtures::POSTAL_CODE)
            ->and($this->addresses->replacements[0]['address']->countryCode)->toBe('MX');
    });

    it('carries the state as the uuid the catalog names it by', function () {
        ($this->update)(new UpdateBusinessSettingsInput(location: SettingsFixtures::location()));

        expect($this->addresses->replacements[0]['address']->stateId)->toBe(SettingsFixtures::STATE_ID)
            ->and($this->addresses->replacements[0]['address']->stateId)->toMatch('/^[0-9a-f-]{36}$/i');
    });

    it('keeps the coordinates as the decimal strings the column stores', function () {
        ($this->update)(new UpdateBusinessSettingsInput(location: SettingsFixtures::location()));

        expect($this->addresses->replacements[0]['address']->latitude)->toBe(SettingsFixtures::LATITUDE)
            ->and($this->addresses->replacements[0]['address']->longitude)->toBe(SettingsFixtures::LONGITUDE);
    });

    it('files an address with no city and no postal code, because a street alone names a place', function () {
        ($this->update)(new UpdateBusinessSettingsInput(location: SettingsFixtures::location(
            city: null,
            postalCode: null,
        )));

        expect($this->addresses->replacements)->toHaveCount(1)
            ->and($this->addresses->replacements[0]['address']->street)->toBe(SettingsFixtures::STREET)
            ->and($this->addresses->replacements[0]['address']->city)->toBeNull()
            ->and($this->addresses->replacements[0]['address']->postalCode)->toBeNull();
    });

    it('files nothing for a business with no address that left the block blank', function () {
        ($this->update)(new UpdateBusinessSettingsInput(location: SettingsFixtures::location(
            street: '',
            city: null,
            postalCode: null,
            latitude: null,
            longitude: null,
        )));

        expect($this->addresses->wasWritten())->toBeFalse()
            ->and($this->addresses->forBusiness(FakeBusinessContext::BUSINESS_ID))->toBeNull();
    });

    it('files nothing when the blank block still carried a pin, because a point alone is not a place', function () {
        ($this->update)(new UpdateBusinessSettingsInput(location: SettingsFixtures::location(
            street: '   ',
            city: null,
            postalCode: null,
        )));

        expect($this->addresses->wasWritten())->toBeFalse();
    });

    it('still changes the timezone when the address block arrived blank', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput(location: SettingsFixtures::location(
            street: '',
            city: null,
            postalCode: null,
            timezone: 'UTC',
        )))->value();

        expect($data->timezone)->toBe('UTC')
            ->and($data->address)->toBeNull();
    });
});

describe('the booking policy', function () {
    it('hands the submitted policy to the port that owns it, exactly once', function () {
        ($this->update)(new UpdateBusinessSettingsInput(bookingPolicy: SettingsFixtures::bookingPolicyInput()));

        expect($this->bookingPolicies->applications)->toHaveCount(1)
            ->and($this->bookingPolicies->applications[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('carries every value the client submitted into the preferences the port receives', function () {
        ($this->update)(new UpdateBusinessSettingsInput(bookingPolicy: SettingsFixtures::bookingPolicyInput()));

        $preferences = $this->bookingPolicies->applications[0]['preferences'];

        expect($preferences)->toBeInstanceOf(BookingPolicyPreferences::class)
            ->and($preferences->leadTimeMinutes)->toBe(SettingsFixtures::LEAD_TIME_MINUTES)
            ->and($preferences->bookingWindowMinutes)->toBe(SettingsFixtures::BOOKING_WINDOW_MINUTES)
            ->and($preferences->slotGranularityMinutes)->toBe(SettingsFixtures::SLOT_GRANULARITY_MINUTES)
            ->and($preferences->cancellationWindowMinutes)->toBe(SettingsFixtures::CANCELLATION_WINDOW_MINUTES)
            ->and($preferences->policyMessage)->toBe(SettingsFixtures::POLICY_MESSAGE)
            ->and($preferences->displayOnBookingPage)->toBeTrue();
    });

    it('leaves the policy untouched when the patch carried no booking policy section', function (UpdateBusinessSettingsInput $input) {
        ($this->update)($input);

        expect($this->bookingPolicies->applications)->toBe([]);
    })->with([
        'nothing at all' => fn () => new UpdateBusinessSettingsInput,
        'appearance only' => fn () => new UpdateBusinessSettingsInput(appearance: SettingsFixtures::appearance()),
        'brand only' => fn () => new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand()),
        'schedule only' => fn () => new UpdateBusinessSettingsInput(schedule: SettingsFixtures::schedule()),
    ]);

    it('answers with the policy as it now stands', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput(
            bookingPolicy: SettingsFixtures::bookingPolicyInput(leadTimeMinutes: 90),
        ))->value();

        expect($data->bookingPolicy)->toBeInstanceOf(BookingPolicySnapshot::class)
            ->and($data->bookingPolicy->leadTimeMinutes)->toBe(90)
            ->and($data->bookingPolicy->displayOnBookingPage)->toBeTrue();
    });

    it('answers with the policy the business already had when the patch left it out', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand()))->value();

        expect($data->bookingPolicy->leadTimeMinutes)->toBe(0)
            ->and($data->bookingPolicy->bookingWindowMinutes)->toBeNull()
            ->and($data->bookingPolicy->slotGranularityMinutes)->toBe(15)
            ->and($data->bookingPolicy->cancellationWindowMinutes)->toBe(120)
            ->and($data->bookingPolicy->displayOnBookingPage)->toBeFalse();
    });

    it('keeps an unlimited window and a cancellation nobody may use as null, never as zero', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput(bookingPolicy: SettingsFixtures::bookingPolicyInput(
            bookingWindowMinutes: null,
            cancellationWindowMinutes: null,
        )))->value();

        $preferences = $this->bookingPolicies->applications[0]['preferences'];

        expect($preferences->bookingWindowMinutes)->toBeNull()
            ->and($preferences->cancellationWindowMinutes)->toBeNull()
            ->and($data->bookingPolicy->bookingWindowMinutes)->toBeNull()
            ->and($data->bookingPolicy->cancellationWindowMinutes)->toBeNull();
    });

    it('carries a refusal the policy port raised back to the caller', function () {
        $this->bookingPolicies->failingOnApply(InvalidLeadTime::negative(-1));

        $response = ($this->update)(new UpdateBusinessSettingsInput(
            bookingPolicy: SettingsFixtures::bookingPolicyInput(leadTimeMinutes: -1),
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_lead_time')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
    });

    it('never reaches the sections that come after a policy the port refused', function () {
        $this->bookingPolicies->failingOnApply(InvalidLeadTime::negative(-1));

        ($this->update)(SettingsFixtures::everything());

        expect($this->schedule->replacements)->toBe([])
            ->and($this->links->replacements)->toBe([]);
    });

    it('leaves another business policy alone', function () {
        $this->bookingPolicies->store(
            SettingsFixtures::OTHER_BUSINESS_ID,
            SettingsFixtures::bookingPolicy(leadTimeMinutes: 15),
        );

        ($this->update)(new UpdateBusinessSettingsInput(bookingPolicy: SettingsFixtures::bookingPolicyInput()));

        expect($this->bookingPolicies->forBusiness(SettingsFixtures::OTHER_BUSINESS_ID)->leadTimeMinutes)->toBe(15);
    });
});

dataset('changed booking policies', [
    'a longer lead time' => fn () => SettingsFixtures::bookingPolicyInput(leadTimeMinutes: 90),
    'no lead time at all' => fn () => SettingsFixtures::bookingPolicyInput(leadTimeMinutes: 0),
    'a shorter window' => fn () => SettingsFixtures::bookingPolicyInput(bookingWindowMinutes: 10080),
    'an unlimited window' => fn () => SettingsFixtures::bookingPolicyInput(bookingWindowMinutes: null),
    'a finer granularity' => fn () => SettingsFixtures::bookingPolicyInput(slotGranularityMinutes: 15),
    'a shorter cancellation window' => fn () => SettingsFixtures::bookingPolicyInput(cancellationWindowMinutes: 60),
    'no cancellation at all' => fn () => SettingsFixtures::bookingPolicyInput(cancellationWindowMinutes: null),
    'another policy message' => fn () => SettingsFixtures::bookingPolicyInput(policyMessage: 'Llega cinco minutos antes.'),
    'no policy message' => fn () => SettingsFixtures::bookingPolicyInput(policyMessage: null),
    'the policy hidden from the booking page' => fn () => SettingsFixtures::bookingPolicyInput(displayOnBookingPage: false),
]);

dataset('changed contact fields', [
    'the phone made optional' => fn () => SettingsFixtures::contactFieldsInput(phone: 'optional'),
    'the email made optional' => fn () => SettingsFixtures::contactFieldsInput(email: 'optional'),
    'the address made required' => fn () => SettingsFixtures::contactFieldsInput(address: 'required'),
]);

describe('the booking preferences the plan allows', function () {
    beforeEach(function () {
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
            ));

        $this->expectRefusedForThePlan = fn (UseCaseResponse $response) => expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_rules_require_complete_plan')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden);
    });

    describe('on the free plan', function () {
        beforeEach(function () {
            ($this->onFreePlan)();
        });

        it('saves the booking policy resubmitted exactly as it is stored', function () {
            $response = ($this->update)(new UpdateBusinessSettingsInput(bookingPolicy: SettingsFixtures::bookingPolicyInput()));

            expect($response->succeeded())->toBeTrue()
                ->and($this->bookingPolicies->applications)->toHaveCount(1)
                ->and($this->bookingPolicies->applications[0]['preferences'])->toEqual(SettingsFixtures::bookingPolicyInput()->toPreferences());
        });

        it('saves the contact fields resubmitted exactly as they are stored', function () {
            $response = ($this->update)(new UpdateBusinessSettingsInput(contactFields: SettingsFixtures::contactFieldsInput()));

            expect($response->succeeded())->toBeTrue()
                ->and($this->bookingPolicies->contactFieldApplications)->toHaveCount(1)
                ->and($this->bookingPolicies->contactFieldApplications[0]['preferences'])->toEqual(SettingsFixtures::contactFieldsInput()->toPreferences());
        });

        it('saves every other section of a patch that resubmits both blocks unchanged', function () {
            $response = ($this->update)(SettingsFixtures::everything());

            expect($response->succeeded())->toBeTrue()
                ->and($this->businesses->saved)->toHaveCount(1)
                ->and($this->phones->replacements)->toHaveCount(1)
                ->and($this->addresses->replacements)->toHaveCount(1)
                ->and($this->bookingPages->applications)->toHaveCount(1)
                ->and($this->schedule->replacements)->toHaveCount(1)
                ->and($this->links->replacements)->toHaveCount(1);
        });

        it('keeps an unlimited window unlimited without a refusal', function () {
            $this->bookingPolicies->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::bookingPolicy(
                leadTimeMinutes: SettingsFixtures::LEAD_TIME_MINUTES,
                bookingWindowMinutes: null,
                slotGranularityMinutes: SettingsFixtures::SLOT_GRANULARITY_MINUTES,
                cancellationWindowMinutes: SettingsFixtures::CANCELLATION_WINDOW_MINUTES,
                policyMessage: SettingsFixtures::POLICY_MESSAGE,
                displayOnBookingPage: true,
            ));

            $response = ($this->update)(new UpdateBusinessSettingsInput(
                bookingPolicy: SettingsFixtures::bookingPolicyInput(bookingWindowMinutes: null),
            ));

            expect($response->succeeded())->toBeTrue()
                ->and($this->bookingPolicies->applications[0]['preferences']->bookingWindowMinutes)->toBeNull();
        });

        it('treats a message that differs only in how it is padded or how it says none as unchanged', function (?string $stored, ?string $submitted) {
            $this->bookingPolicies->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::bookingPolicy(
                leadTimeMinutes: SettingsFixtures::LEAD_TIME_MINUTES,
                bookingWindowMinutes: SettingsFixtures::BOOKING_WINDOW_MINUTES,
                slotGranularityMinutes: SettingsFixtures::SLOT_GRANULARITY_MINUTES,
                cancellationWindowMinutes: SettingsFixtures::CANCELLATION_WINDOW_MINUTES,
                policyMessage: $stored,
                displayOnBookingPage: true,
            ));

            $response = ($this->update)(new UpdateBusinessSettingsInput(
                bookingPolicy: SettingsFixtures::bookingPolicyInput(policyMessage: $submitted),
            ));

            expect($response->succeeded())->toBeTrue()
                ->and($this->bookingPolicies->applications)->toHaveCount(1);
        })->with([
            'padded with spaces' => [SettingsFixtures::POLICY_MESSAGE, '  '.SettingsFixtures::POLICY_MESSAGE.'  '],
            'padded with a newline' => [SettingsFixtures::POLICY_MESSAGE, SettingsFixtures::POLICY_MESSAGE."\n"],
            'empty against none' => [null, ''],
            'whitespace against none' => [null, '   '],
        ]);

        it('refuses a change to any one of the booking policy fields and writes nothing', function (BookingPolicyInput $submitted) {
            ($this->expectRefusedForThePlan)(($this->update)(new UpdateBusinessSettingsInput(bookingPolicy: $submitted)));

            ($this->expectNothingWritten)();
        })->with('changed booking policies');

        it('refuses a window set on a business whose window was unlimited', function () {
            $this->bookingPolicies->store(FakeBusinessContext::BUSINESS_ID, SettingsFixtures::bookingPolicy(
                leadTimeMinutes: SettingsFixtures::LEAD_TIME_MINUTES,
                bookingWindowMinutes: null,
                slotGranularityMinutes: SettingsFixtures::SLOT_GRANULARITY_MINUTES,
                cancellationWindowMinutes: SettingsFixtures::CANCELLATION_WINDOW_MINUTES,
                policyMessage: SettingsFixtures::POLICY_MESSAGE,
                displayOnBookingPage: true,
            ));

            ($this->expectRefusedForThePlan)(($this->update)(new UpdateBusinessSettingsInput(
                bookingPolicy: SettingsFixtures::bookingPolicyInput(bookingWindowMinutes: SettingsFixtures::BOOKING_WINDOW_MINUTES),
            )));

            ($this->expectNothingWritten)();
        });

        it('refuses a change to any one of the contact fields and writes nothing', function (ContactFieldsInput $submitted) {
            ($this->expectRefusedForThePlan)(($this->update)(new UpdateBusinessSettingsInput(contactFields: $submitted)));

            ($this->expectNothingWritten)();
        })->with('changed contact fields');

        it('refuses changed contact fields even beside an unchanged booking policy', function () {
            ($this->expectRefusedForThePlan)(($this->update)(new UpdateBusinessSettingsInput(
                bookingPolicy: SettingsFixtures::bookingPolicyInput(),
                contactFields: SettingsFixtures::contactFieldsInput(address: 'required'),
            )));

            ($this->expectNothingWritten)();
        });

        it('refuses a changed booking policy even beside unchanged contact fields', function () {
            ($this->expectRefusedForThePlan)(($this->update)(new UpdateBusinessSettingsInput(
                bookingPolicy: SettingsFixtures::bookingPolicyInput(leadTimeMinutes: 90),
                contactFields: SettingsFixtures::contactFieldsInput(),
            )));

            ($this->expectNothingWritten)();
        });

        it('writes none of the brand, contact or location that arrived in the same refused patch', function () {
            ($this->expectRefusedForThePlan)(($this->update)(new UpdateBusinessSettingsInput(
                brand: SettingsFixtures::brand(
                    name: 'Barbería del Centro',
                    slug: 'barberia-del-centro',
                    industryId: SettingsFixtures::OTHER_INDUSTRY_ID,
                ),
                appearance: SettingsFixtures::appearance(),
                contact: SettingsFixtures::contact(phone: SettingsFixtures::submittedPhone()),
                location: SettingsFixtures::location(timezone: 'America/Mexico_City'),
                schedule: SettingsFixtures::schedule(),
                links: SettingsFixtures::links(),
                bookingPolicy: SettingsFixtures::bookingPolicyInput(slotGranularityMinutes: 15),
            )));

            ($this->expectNothingWritten)();

            expect($this->businesses->nameChecks)->toBe([])
                ->and($this->businesses->slugChecks)->toBe([])
                ->and($this->industries->wasConsulted())->toBeFalse()
                ->and($this->parser->wasConsulted())->toBeFalse();
        });

        it('asks the allowance about the business in context once, whichever booking block the patch carried', function (UpdateBusinessSettingsInput $input) {
            ($this->update)($input);

            expect($this->bookingRules->consultations)->toBe([FakeBusinessContext::BUSINESS_ID]);
        })->with([
            'the booking policy only' => fn () => new UpdateBusinessSettingsInput(bookingPolicy: SettingsFixtures::bookingPolicyInput()),
            'the contact fields only' => fn () => new UpdateBusinessSettingsInput(contactFields: SettingsFixtures::contactFieldsInput()),
            'both blocks' => fn () => new UpdateBusinessSettingsInput(
                bookingPolicy: SettingsFixtures::bookingPolicyInput(),
                contactFields: SettingsFixtures::contactFieldsInput(),
            ),
        ]);

        it('compares against the policy of the business in context, never another one', function () {
            $this->bookingPolicies->store(
                SettingsFixtures::OTHER_BUSINESS_ID,
                SettingsFixtures::bookingPolicy(leadTimeMinutes: 90, bookingWindowMinutes: 10080),
            );

            ($this->expectRefusedForThePlan)(($this->update)(new UpdateBusinessSettingsInput(
                bookingPolicy: SettingsFixtures::bookingPolicyInput(leadTimeMinutes: 90, bookingWindowMinutes: 10080),
            )));

            expect($this->bookingPolicies->reads)->not->toContain(SettingsFixtures::OTHER_BUSINESS_ID);
        });

        it('compares against the contact fields of the business in context, never another one', function () {
            $this->bookingPolicies->storeContactFields(
                SettingsFixtures::OTHER_BUSINESS_ID,
                SettingsFixtures::contactFields(
                    phone: ContactFieldPreference::Hidden,
                    email: ContactFieldPreference::Required,
                    address: ContactFieldPreference::Required,
                ),
            );

            ($this->expectRefusedForThePlan)(($this->update)(new UpdateBusinessSettingsInput(
                contactFields: SettingsFixtures::contactFieldsInput(address: 'required'),
            )));

            expect($this->bookingPolicies->contactFieldReads)->not->toContain(SettingsFixtures::OTHER_BUSINESS_ID);
        });

        it('answers with the platform defaults the free plan runs on, not with what it stored', function () {
            $data = ($this->update)(new UpdateBusinessSettingsInput(
                bookingPolicy: SettingsFixtures::bookingPolicyInput(),
                contactFields: SettingsFixtures::contactFieldsInput(),
            ))->value();

            expect($data->bookingPolicy->leadTimeMinutes)->toBe(0)
                ->and($data->bookingPolicy->bookingWindowMinutes)->toBeNull()
                ->and($data->bookingPolicy->displayOnBookingPage)->toBeFalse()
                ->and($data->contactFields->phone)->toBe(ContactFieldPreference::Required)
                ->and($data->contactFields->address)->toBe(ContactFieldPreference::Hidden);
        });
    });

    describe('on the complete plan', function () {
        it('saves a change to any one of the booking policy fields', function (BookingPolicyInput $submitted) {
            $response = ($this->update)(new UpdateBusinessSettingsInput(bookingPolicy: $submitted));

            expect($response->succeeded())->toBeTrue()
                ->and($this->bookingPolicies->applications)->toHaveCount(1)
                ->and($this->bookingPolicies->applications[0]['preferences'])->toEqual($submitted->toPreferences())
                ->and($response->value()->bookingPolicy->leadTimeMinutes)->toBe($submitted->leadTimeMinutes)
                ->and($response->value()->bookingPolicy->bookingWindowMinutes)->toBe($submitted->bookingWindowMinutes)
                ->and($response->value()->bookingPolicy->slotGranularityMinutes)->toBe($submitted->slotGranularityMinutes)
                ->and($response->value()->bookingPolicy->cancellationWindowMinutes)->toBe($submitted->cancellationWindowMinutes)
                ->and($response->value()->bookingPolicy->policyMessage)->toBe($submitted->policyMessage)
                ->and($response->value()->bookingPolicy->displayOnBookingPage)->toBe($submitted->displayOnBookingPage);
        })->with('changed booking policies');

        it('saves a change to any one of the contact fields', function (ContactFieldsInput $submitted) {
            $response = ($this->update)(new UpdateBusinessSettingsInput(contactFields: $submitted));

            expect($response->succeeded())->toBeTrue()
                ->and($this->bookingPolicies->contactFieldApplications)->toHaveCount(1)
                ->and($this->bookingPolicies->contactFieldApplications[0]['preferences'])->toEqual($submitted->toPreferences())
                ->and($response->value()->contactFields)->toEqual($submitted->toPreferences());
        })->with('changed contact fields');

        it('asks the allowance about the business in context once', function () {
            ($this->update)(new UpdateBusinessSettingsInput(
                bookingPolicy: SettingsFixtures::bookingPolicyInput(leadTimeMinutes: 90),
                contactFields: SettingsFixtures::contactFieldsInput(address: 'required'),
            ));

            expect($this->bookingRules->consultations)->toBe([FakeBusinessContext::BUSINESS_ID]);
        });
    });

    it('never consults the allowance when the patch carried neither booking block', function (UpdateBusinessSettingsInput $input) {
        ($this->onFreePlan)();

        $response = ($this->update)($input);

        expect($response->succeeded())->toBeTrue()
            ->and($this->bookingRules->consultations)->toBe([]);
    })->with([
        'nothing at all' => fn () => new UpdateBusinessSettingsInput,
        'brand only' => fn () => new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand()),
        'appearance only' => fn () => new UpdateBusinessSettingsInput(appearance: SettingsFixtures::appearance()),
        'contact only' => fn () => new UpdateBusinessSettingsInput(contact: SettingsFixtures::contact()),
        'location only' => fn () => new UpdateBusinessSettingsInput(location: SettingsFixtures::location()),
        'schedule only' => fn () => new UpdateBusinessSettingsInput(schedule: SettingsFixtures::schedule()),
        'links only' => fn () => new UpdateBusinessSettingsInput(links: SettingsFixtures::links()),
    ]);
});

describe('the contact fields', function () {
    it('hands the submitted requirements to the port that owns them, exactly once', function () {
        ($this->update)(new UpdateBusinessSettingsInput(contactFields: SettingsFixtures::contactFieldsInput()));

        expect($this->bookingPolicies->contactFieldApplications)->toHaveCount(1)
            ->and($this->bookingPolicies->contactFieldApplications[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('translates each submitted string into the preference of the field it was sent for', function () {
        ($this->update)(new UpdateBusinessSettingsInput(contactFields: SettingsFixtures::contactFieldsInput(
            phone: 'hidden',
            email: 'required',
            address: 'optional',
        )));

        $preferences = $this->bookingPolicies->contactFieldApplications[0]['preferences'];

        expect($preferences)->toBeInstanceOf(ContactFieldPreferences::class)
            ->and($preferences->phone)->toBe(ContactFieldPreference::Hidden)
            ->and($preferences->email)->toBe(ContactFieldPreference::Required)
            ->and($preferences->address)->toBe(ContactFieldPreference::Optional);
    });

    it('leaves the contact fields untouched when the patch carried no contact fields section', function (UpdateBusinessSettingsInput $input) {
        ($this->update)($input);

        expect($this->bookingPolicies->contactFieldApplications)->toBe([]);
    })->with([
        'nothing at all' => fn () => new UpdateBusinessSettingsInput,
        'booking policy only' => fn () => new UpdateBusinessSettingsInput(bookingPolicy: SettingsFixtures::bookingPolicyInput()),
        'brand only' => fn () => new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand()),
        'appearance only' => fn () => new UpdateBusinessSettingsInput(appearance: SettingsFixtures::appearance()),
    ]);

    it('leaves the booking rules untouched when only the contact fields were sent', function () {
        ($this->update)(new UpdateBusinessSettingsInput(contactFields: SettingsFixtures::contactFieldsInput()));

        expect($this->bookingPolicies->applications)->toBe([])
            ->and($this->businesses->saved)->toBe([])
            ->and($this->bookingPages->applications)->toBe([])
            ->and($this->schedule->replacements)->toBe([])
            ->and($this->links->replacements)->toBe([]);
    });

    it('answers with the contact fields as they now stand', function () {
        $contactFields = ($this->update)(new UpdateBusinessSettingsInput(
            contactFields: SettingsFixtures::contactFieldsInput(phone: 'optional', email: 'hidden', address: 'required'),
        ))->value()->contactFields;

        expect($contactFields->phone)->toBe(ContactFieldPreference::Optional)
            ->and($contactFields->email)->toBe(ContactFieldPreference::Hidden)
            ->and($contactFields->address)->toBe(ContactFieldPreference::Required);
    });

    it('answers with the contact fields the business already had when the patch left them out', function () {
        $contactFields = ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand()))
            ->value()
            ->contactFields;

        expect($contactFields->phone)->toBe(ContactFieldPreference::Required)
            ->and($contactFields->email)->toBe(ContactFieldPreference::Optional)
            ->and($contactFields->address)->toBe(ContactFieldPreference::Hidden);
    });

    it('writes the contact fields inside the unit of work, never outside it', function () {
        $transactions = Mockery::mock(TransactionManager::class);
        $transactions->shouldReceive('run')->once()->andReturnNull();

        ($this->useCaseWith)($transactions)->handle(new UpdateBusinessSettingsInput(
            contactFields: SettingsFixtures::contactFieldsInput(),
        ));

        expect($this->bookingPolicies->contactFieldApplications)->toBe([]);
    });

    it('never reaches the contact fields when the booking policy port refused', function () {
        $this->bookingPolicies->failingOnApply(InvalidLeadTime::negative(-1));

        ($this->update)(SettingsFixtures::everything());

        expect($this->bookingPolicies->contactFieldApplications)->toBe([]);
    });

    it('applies the booking policy before the contact fields', function () {
        $this->bookingPolicies->failingOnApplyContactFields(BookingPolicyNotFound::withId(FakeBusinessContext::BUSINESS_ID));

        ($this->update)(SettingsFixtures::everything());

        expect($this->bookingPolicies->applications)->toHaveCount(1);
    });

    it('carries a refusal the contact fields port raised back to the caller', function () {
        $this->bookingPolicies->failingOnApplyContactFields(BookingPolicyNotFound::withId(FakeBusinessContext::BUSINESS_ID));

        $response = ($this->update)(new UpdateBusinessSettingsInput(
            contactFields: SettingsFixtures::contactFieldsInput(),
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_policy_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('never reaches the schedule or the links once the contact fields port refused', function () {
        $this->bookingPolicies->failingOnApplyContactFields(BookingPolicyNotFound::withId(FakeBusinessContext::BUSINESS_ID));

        ($this->update)(SettingsFixtures::everything());

        expect($this->schedule->replacements)->toBe([])
            ->and($this->links->replacements)->toBe([]);
    });

    it('leaves another business contact fields alone', function () {
        $this->bookingPolicies->storeContactFields(
            SettingsFixtures::OTHER_BUSINESS_ID,
            SettingsFixtures::contactFields(address: ContactFieldPreference::Required),
        );

        ($this->update)(new UpdateBusinessSettingsInput(contactFields: SettingsFixtures::contactFieldsInput()));

        expect($this->bookingPolicies->contactFieldsFor(SettingsFixtures::OTHER_BUSINESS_ID)->address)
            ->toBe(ContactFieldPreference::Required)
            ->and(array_column($this->bookingPolicies->contactFieldApplications, 'businessId'))
            ->not->toContain(SettingsFixtures::OTHER_BUSINESS_ID);
    });
});

describe('patching one section at a time', function () {
    it('never calls the port of a section the client left out', function () {
        ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand()));

        expect($this->phones->replacements)->toBe([])
            ->and($this->addresses->wasWritten())->toBeFalse()
            ->and($this->bookingPages->applications)->toBe([])
            ->and($this->bookingPolicies->applications)->toBe([])
            ->and($this->bookingPolicies->contactFieldApplications)->toBe([])
            ->and($this->schedule->replacements)->toBe([])
            ->and($this->links->replacements)->toBe([]);
    });

    it('changes only the appearance when only the appearance was sent', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput(
            appearance: SettingsFixtures::appearance(accentColor: 'teal', buttonShape: 'rounded', theme: 'dark'),
        ))->value();

        expect($this->bookingPages->applications)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accentColor' => 'teal',
            'buttonShape' => 'rounded',
            'theme' => 'dark',
        ]])
            ->and($data->name)->toBe(OnboardingFixtures::NAME)
            ->and($this->businesses->saved)->toBe([])
            ->and($this->addresses->wasWritten())->toBeFalse();
    });

    it('replaces the whole week when only the schedule was sent', function () {
        ($this->update)(new UpdateBusinessSettingsInput(schedule: SettingsFixtures::schedule(
            SettingsFixtures::entry(weekday: 1),
            SettingsFixtures::entry(weekday: 1, startsAt: '16:00', endsAt: '20:00'),
        )));

        expect($this->schedule->replacements)->toHaveCount(1)
            ->and($this->schedule->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->schedule->replacements[0]['entries'])->toHaveCount(2)
            ->and($this->businesses->saved)->toBe([])
            ->and($this->links->replacements)->toBe([]);
    });

    it('closes the week when the schedule sent is empty', function () {
        ($this->update)(new UpdateBusinessSettingsInput(schedule: new ScheduleInput([])));

        expect($this->schedule->replacements)->toHaveCount(1)
            ->and($this->schedule->replacements[0]['entries'])->toBe([]);
    });

    it('clears the links when the list sent is empty', function () {
        ($this->update)(new UpdateBusinessSettingsInput(links: new LinksInput([])));

        expect($this->links->replacements)->toHaveCount(1)
            ->and($this->links->replacements[0]['links'])->toBe([])
            ->and($this->businesses->saved)->toBe([]);
    });

    it('answers with the settings as they stand when the client sent nothing to change', function () {
        $data = ($this->update)(new UpdateBusinessSettingsInput)->value();

        expect($data->name)->toBe(OnboardingFixtures::NAME)
            ->and($data->slug)->toBe(OnboardingFixtures::SLUG)
            ->and($this->transactions->runs())->toBe(1)
            ->and($this->businesses->saved)->toBe([])
            ->and($this->phones->replacements)->toBe([])
            ->and($this->addresses->wasWritten())->toBeFalse()
            ->and($this->bookingPages->applications)->toBe([])
            ->and($this->bookingPolicies->applications)->toBe([])
            ->and($this->bookingPolicies->contactFieldApplications)->toBe([])
            ->and($this->schedule->replacements)->toBe([])
            ->and($this->links->replacements)->toBe([]);
    });
});

describe('leaving the business row alone', function () {
    it('never writes the row for a patch that changes none of its own fields', function (UpdateBusinessSettingsInput $input) {
        ($this->update)($input);

        expect($this->businesses->saved)->toBe([]);
    })->with([
        'nothing at all' => fn () => new UpdateBusinessSettingsInput,
        'appearance only' => fn () => new UpdateBusinessSettingsInput(appearance: SettingsFixtures::appearance()),
        'schedule only' => fn () => new UpdateBusinessSettingsInput(
            schedule: SettingsFixtures::schedule(SettingsFixtures::entry()),
        ),
        'an empty schedule only' => fn () => new UpdateBusinessSettingsInput(schedule: new ScheduleInput([])),
        'links only' => fn () => new UpdateBusinessSettingsInput(links: SettingsFixtures::links(SettingsFixtures::link())),
        'an empty link list only' => fn () => new UpdateBusinessSettingsInput(links: new LinksInput([])),
        'booking policy only' => fn () => new UpdateBusinessSettingsInput(
            bookingPolicy: SettingsFixtures::bookingPolicyInput(),
        ),
        'contact fields only' => fn () => new UpdateBusinessSettingsInput(
            contactFields: SettingsFixtures::contactFieldsInput(),
        ),
        'appearance, schedule and links together' => fn () => new UpdateBusinessSettingsInput(
            appearance: SettingsFixtures::appearance(),
            schedule: new ScheduleInput([]),
            links: new LinksInput([]),
        ),
    ]);

    it('still writes the row for a patch that changes a field the row holds', function (UpdateBusinessSettingsInput $input) {
        ($this->update)($input);

        expect($this->businesses->saved)->toHaveCount(1)
            ->and($this->businesses->saved[0]->id)->toBe(FakeBusinessContext::BUSINESS_ID);
    })->with([
        'brand only' => fn () => new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand()),
        'contact only' => fn () => new UpdateBusinessSettingsInput(contact: SettingsFixtures::contact()),
        'location only' => fn () => new UpdateBusinessSettingsInput(location: SettingsFixtures::location()),
    ]);

    it('writes the row once when a section it holds arrives beside sections it does not', function () {
        ($this->update)(new UpdateBusinessSettingsInput(
            brand: SettingsFixtures::brand(),
            appearance: SettingsFixtures::appearance(),
            links: new LinksInput([]),
        ));

        expect($this->businesses->saved)->toHaveCount(1)
            ->and($this->bookingPages->applications)->toHaveCount(1)
            ->and($this->links->replacements)->toHaveCount(1);
    });
});

describe('refusing an update', function () {
    it('refuses what the input itself refuses, before it opens a transaction', function (UpdateBusinessSettingsInput $input, string $code) {
        $response = ($this->update)($input);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->businesses->idsRead)->toBe([]);

        ($this->expectNothingWritten)();
    })->with([
        'a blank name' => [
            new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(name: '   ')),
            'invalid_business_name',
        ],
        'a slug the URL could not carry' => [
            new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(slug: 'Barbería Ñandú')),
            'invalid_business_slug',
        ],
        'an industry id that is not a uuid' => [
            new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(industryId: '7')),
            'unknown_industry',
        ],
        'a description longer than the column' => [
            new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(about: str_repeat('a', 2001))),
            'invalid_business_about',
        ],
        'a malformed contact email' => [
            new UpdateBusinessSettingsInput(contact: SettingsFixtures::contact(contactEmail: 'nope')),
            'invalid_business_contact_email',
        ],
        'a coordinate that is not a number' => [
            new UpdateBusinessSettingsInput(location: SettingsFixtures::location(latitude: 'north')),
            'invalid_business_coordinates',
        ],
        'half a coordinate' => [
            new UpdateBusinessSettingsInput(location: SettingsFixtures::location(longitude: null)),
            'invalid_business_coordinates',
        ],
        'a currency that is not a code' => [
            new UpdateBusinessSettingsInput(location: SettingsFixtures::location(currencyCode: 'pesos')),
            'invalid_business_currency',
        ],
        'a timezone nobody keeps' => [
            new UpdateBusinessSettingsInput(location: SettingsFixtures::location(timezone: '+02:00')),
            'invalid_timezone',
        ],
        'contact fields missing a field' => [
            new UpdateBusinessSettingsInput(contactFields: ContactFieldsInput::fromPayload(['phone' => 'required'])),
            'incomplete_contact_fields',
        ],
        'a contact field requirement nobody declared' => [
            new UpdateBusinessSettingsInput(contactFields: SettingsFixtures::contactFieldsInput(address: 'mandatory')),
            'invalid_contact_field_requirement',
        ],
    ]);

    it('answers with a failure when the business in context is not on record', function () {
        $elsewhere = (new FakeBusinessRepository)
            ->store(OnboardingFixtures::business(id: SettingsFixtures::OTHER_BUSINESS_ID));

        $useCase = new UpdateBusinessSettings(
            $elsewhere,
            $this->industries,
            $this->addresses,
            $this->links,
            $this->schedule,
            $this->bookingPages,
            $this->bookingPolicies,
            $this->bookingRules,
            $this->phones,
            ($this->presenterOver)($elsewhere),
            $this->parser,
            new FakeBusinessContext,
            $this->transactions,
        );

        $response = $useCase->handle(SettingsFixtures::everything());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($elsewhere->saved)->toBe([]);
    });

    it('carries a refusal another domain raised through its port back to the caller', function (Throwable $refusal, string $code, DomainFailureKind $kind) {
        $this->schedule->failingOnReplace($refusal);

        $response = ($this->update)(SettingsFixtures::everything());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind);
    })->with([
        'overlapping intervals' => [
            OverlappingScheduleIntervals::onWeekday(1),
            'overlapping_schedule_intervals',
            DomainFailureKind::Conflict,
        ],
    ]);

    it('carries back a refusal the address book raised, having written nothing after it', function (Throwable $refusal, string $code, DomainFailureKind $kind) {
        $this->addresses->failingOnReplace($refusal);

        $response = ($this->update)(SettingsFixtures::everything());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind)
            ->and($this->businesses->saved)->toBe([])
            ->and($this->bookingPages->applications)->toBe([])
            ->and($this->bookingPolicies->applications)->toBe([])
            ->and($this->bookingPolicies->contactFieldApplications)->toBe([])
            ->and($this->schedule->replacements)->toBe([])
            ->and($this->links->replacements)->toBe([]);
    })->with([
        'a street it will not blank, now that this form deletes no address' => [
            InvalidAddressStreet::empty(),
            'invalid_address_street',
            DomainFailureKind::Invalid,
        ],
        'a city already on file it will not clear' => [
            AddressCityCannotBeCleared::alreadySet(),
            'address_city_cannot_be_cleared',
            DomainFailureKind::Conflict,
        ],
        'a postal code already on file it will not clear' => [
            AddressPostalCodeCannotBeCleared::alreadySet(),
            'address_postal_code_cannot_be_cleared',
            DomainFailureKind::Conflict,
        ],
    ]);

    it('stops at the section that refused and never reaches the ones after it', function () {
        $this->schedule->failingOnReplace(OverlappingScheduleIntervals::onWeekday(1));

        $response = ($this->update)(SettingsFixtures::everything());

        expect($response->failed())->toBeTrue()
            ->and($this->links->replacements)->toBe([]);
    });

    it('never touches a later port when an earlier one refused', function () {
        $this->addresses->failingOnReplace(OverlappingScheduleIntervals::onWeekday(1));

        $response = ($this->update)(SettingsFixtures::everything());

        expect($response->failed())->toBeTrue()
            ->and($this->businesses->saved)->toBe([])
            ->and($this->bookingPages->applications)->toBe([])
            ->and($this->bookingPolicies->applications)->toBe([])
            ->and($this->bookingPolicies->contactFieldApplications)->toBe([])
            ->and($this->schedule->replacements)->toBe([])
            ->and($this->links->replacements)->toBe([]);
    });

    it('lets the refusal escape the transaction, so the database rolls the whole update back', function () {
        $escaped = null;
        $transactions = Mockery::mock(TransactionManager::class);
        $transactions->shouldReceive('run')->once()->andReturnUsing(
            function (callable $work) use (&$escaped) {
                try {
                    return $work();
                } catch (Throwable $failure) {
                    $escaped = $failure;

                    throw $failure;
                }
            },
        );

        $this->links->failingOnReplace(InvalidLinkPlatform::withValue('myspace'));

        $response = ($this->useCaseWith)($transactions)->handle(SettingsFixtures::everything());

        expect($escaped)->toBeInstanceOf(InvalidLinkPlatform::class)
            ->and($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_link_platform')
            ->and($response->error()->cause())->toBe($escaped);
    });

    it('answers with no data at all when it refused', function () {
        $response = ($this->update)(new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(name: '')));

        expect($response->succeeded())->toBeFalse()
            ->and(fn () => $response->value())->toThrow(InvalidBusinessName::class);
    });
});

describe('the business it belongs to', function () {
    it('updates the business in context, never one a caller could name', function () {
        ($this->update)(SettingsFixtures::everything());

        expect(array_unique($this->businesses->idsRead))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->businesses->saved[0]->id)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('scopes every port it writes through to the business in context', function () {
        ($this->update)(SettingsFixtures::everything());

        expect($this->phones->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->addresses->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->bookingPages->applications[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->bookingPolicies->applications[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->bookingPolicies->contactFieldApplications[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->schedule->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->links->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('leaves another business settings exactly as they were', function () {
        $other = OnboardingFixtures::business(
            id: SettingsFixtures::OTHER_BUSINESS_ID,
            name: 'Peluquería Ámbar',
            slug: 'peluqueria-ambar',
        );
        $this->businesses->store($other);
        $this->addresses->store(SettingsFixtures::OTHER_BUSINESS_ID, SettingsFixtures::address(street: 'Calle Ajena 1'));

        ($this->update)(SettingsFixtures::everything());

        expect($other->name())->toBe('Peluquería Ámbar')
            ->and($other->slug())->toBe('peluqueria-ambar')
            ->and($this->addresses->forBusiness(SettingsFixtures::OTHER_BUSINESS_ID)?->street)->toBe('Calle Ajena 1')
            ->and($this->businesses->idsRead)->not->toContain(SettingsFixtures::OTHER_BUSINESS_ID);
    });
});
