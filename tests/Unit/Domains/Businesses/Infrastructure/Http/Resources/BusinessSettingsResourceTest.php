<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessSettingsResource;
use App\Domains\Businesses\ValueObjects\BookingPageImageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;
use App\Domains\Businesses\ValueObjects\BusinessAddressSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use App\Domains\Businesses\ValueObjects\ContactFieldPreference;
use App\Domains\Businesses\ValueObjects\ContactFieldPreferences;
use App\Shared\ValueObjects\PhoneNumber;
use Tests\Support\PhoneNumbers;
use Tests\TestCase;

uses(TestCase::class);

const SETTINGS_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b1';

const SETTINGS_INDUSTRY_ID = '01930000-0000-7000-8000-0000000000f1';

const SETTINGS_STATE_ID = '01930000-0000-7000-8000-0000000000e1';

const SETTINGS_IMAGE_ID = '01930000-0000-7000-8000-0000000000c1';

function businessSettingsBookingPage(
    string $accentColor = 'teal',
    string $buttonShape = 'rounded',
    string $theme = 'dark',
    ?string $bannerUrl = 'https://cdn.mizita.test/banner.jpg',
    ?array $gallery = null,
): BookingPageSnapshot {
    return new BookingPageSnapshot(
        accentColor: $accentColor,
        buttonShape: $buttonShape,
        theme: $theme,
        bannerUrl: $bannerUrl,
        gallery: $gallery ?? [new BookingPageImageSnapshot(id: SETTINGS_IMAGE_ID, url: 'https://cdn.mizita.test/one.jpg')],
    );
}

function businessSettingsBookingPolicy(
    int $leadTimeMinutes = 60,
    ?int $bookingWindowMinutes = 43200,
    int $slotGranularityMinutes = 30,
    ?int $cancellationWindowMinutes = 240,
    ?string $policyMessage = 'Cancela con cuatro horas de antelación.',
    bool $displayOnBookingPage = true,
): BookingPolicySnapshot {
    return new BookingPolicySnapshot(
        leadTimeMinutes: $leadTimeMinutes,
        bookingWindowMinutes: $bookingWindowMinutes,
        slotGranularityMinutes: $slotGranularityMinutes,
        cancellationWindowMinutes: $cancellationWindowMinutes,
        policyMessage: $policyMessage,
        displayOnBookingPage: $displayOnBookingPage,
    );
}

function businessSettingsContactFields(
    ContactFieldPreference $phone = ContactFieldPreference::Hidden,
    ContactFieldPreference $email = ContactFieldPreference::Required,
    ContactFieldPreference $address = ContactFieldPreference::Optional,
): ContactFieldPreferences {
    return new ContactFieldPreferences(phone: $phone, email: $email, address: $address);
}

function businessSettingsAddress(
    ?string $stateId = SETTINGS_STATE_ID,
    ?string $latitude = '19.3627888',
    ?string $longitude = '-99.1768069',
    ?string $city = 'Ciudad de México',
    ?string $postalCode = '03940',
): BusinessAddressSnapshot {
    return new BusinessAddressSnapshot(
        street: 'Avenida Insurgentes Sur 1602',
        city: $city,
        stateId: $stateId,
        postalCode: $postalCode,
        countryCode: 'MX',
        latitude: $latitude,
        longitude: $longitude,
    );
}

function businessSettingsData(
    string $id = SETTINGS_BUSINESS_ID,
    string $name = 'Ada Salon',
    string $slug = 'ada-salon',
    string $industryId = SETTINGS_INDUSTRY_ID,
    string $timezone = 'Europe/Madrid',
    ?string $about = 'Cortes y color desde 2019.',
    ?string $contactEmail = 'hola@ada-salon.com',
    string $currencyCode = 'MXN',
    ?string $logoUrl = 'https://cdn.mizita.test/logo.png',
    ?PhoneNumber $phone = null,
    ?BusinessAddressSnapshot $address = null,
    ?array $schedule = null,
    ?array $links = null,
    ?BookingPageSnapshot $bookingPage = null,
    ?BookingPolicySnapshot $bookingPolicy = null,
    ?ContactFieldPreferences $contactFields = null,
): BusinessSettingsData {
    return new BusinessSettingsData(
        id: $id,
        name: $name,
        slug: $slug,
        industryId: $industryId,
        timezone: $timezone,
        about: $about,
        contactEmail: $contactEmail,
        currencyCode: $currencyCode,
        logoUrl: $logoUrl,
        phone: $phone ?? PhoneNumbers::mexican(),
        address: $address ?? businessSettingsAddress(),
        schedule: $schedule ?? [new BusinessScheduleEntry(weekday: 1, startsAt: '09:00', endsAt: '14:00')],
        links: $links ?? [new BusinessLinkSnapshot(platform: 'instagram', url: 'https://instagram.com/ada.salon', position: 0)],
        bookingPage: $bookingPage ?? businessSettingsBookingPage(),
        bookingPolicy: $bookingPolicy ?? businessSettingsBookingPolicy(),
        contactFields: $contactFields ?? businessSettingsContactFields(),
    );
}

function businessSettingsNothingFiled(): BusinessSettingsData
{
    return new BusinessSettingsData(
        id: SETTINGS_BUSINESS_ID,
        name: 'Ada Salon',
        slug: 'ada-salon',
        industryId: SETTINGS_INDUSTRY_ID,
        timezone: 'Europe/Madrid',
        about: null,
        contactEmail: null,
        currencyCode: 'MXN',
        logoUrl: null,
        phone: null,
        address: null,
        schedule: [],
        links: [],
        bookingPage: businessSettingsBookingPage(bannerUrl: null, gallery: []),
        bookingPolicy: businessSettingsBookingPolicy(
            leadTimeMinutes: 0,
            bookingWindowMinutes: null,
            slotGranularityMinutes: 15,
            cancellationWindowMinutes: null,
            policyMessage: null,
            displayOnBookingPage: false,
        ),
        contactFields: businessSettingsContactFields(
            phone: ContactFieldPreference::Required,
            email: ContactFieldPreference::Optional,
            address: ContactFieldPreference::Hidden,
        ),
    );
}

/**
 * @return array<string, mixed>
 */
function serializedBusinessSettings(?BusinessSettingsData $settings = null): array
{
    return (array) BusinessSettingsResource::make($settings ?? businessSettingsData())
        ->response()
        ->getData(true)['data'];
}

describe('the client contract', function () {
    it('serializes exactly the keys the client contract declares', function () {
        expect(array_keys(serializedBusinessSettings()))->toBe([
            'id',
            'name',
            'slug',
            'industry_id',
            'timezone',
            'about',
            'contact_email',
            'currency_code',
            'logo_url',
            'phone',
            'address',
            'schedule',
            'links',
            'booking_page',
            'booking_policy',
            'contact_fields',
        ]);
    });

    it('wraps the payload in the data envelope', function () {
        expect(BusinessSettingsResource::make(businessSettingsData())->response()->getData(true))
            ->toHaveKey('data');
    });

    it('serializes the whole aggregate the settings screen reads', function () {
        expect(serializedBusinessSettings())->toBe([
            'id' => SETTINGS_BUSINESS_ID,
            'name' => 'Ada Salon',
            'slug' => 'ada-salon',
            'industry_id' => SETTINGS_INDUSTRY_ID,
            'timezone' => 'Europe/Madrid',
            'about' => 'Cortes y color desde 2019.',
            'contact_email' => 'hola@ada-salon.com',
            'currency_code' => 'MXN',
            'logo_url' => 'https://cdn.mizita.test/logo.png',
            'phone' => [
                'country_code' => 'MX',
                'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
                'e164' => PhoneNumbers::MX_E164,
            ],
            'address' => [
                'street' => 'Avenida Insurgentes Sur 1602',
                'city' => 'Ciudad de México',
                'state_id' => SETTINGS_STATE_ID,
                'postal_code' => '03940',
                'country_code' => 'MX',
                'latitude' => '19.3627888',
                'longitude' => '-99.1768069',
            ],
            'schedule' => [
                ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '14:00'],
            ],
            'links' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/ada.salon', 'position' => 0],
            ],
            'booking_page' => [
                'accent_color' => 'teal',
                'button_shape' => 'rounded',
                'theme' => 'dark',
                'banner_url' => 'https://cdn.mizita.test/banner.jpg',
                'gallery' => [
                    ['id' => SETTINGS_IMAGE_ID, 'url' => 'https://cdn.mizita.test/one.jpg'],
                ],
            ],
            'booking_policy' => [
                'lead_time_minutes' => 60,
                'booking_window_minutes' => 43200,
                'slot_granularity_minutes' => 30,
                'cancellation_window_minutes' => 240,
                'policy_message' => 'Cancela con cuatro horas de antelación.',
                'display_on_booking_page' => true,
            ],
            'contact_fields' => [
                'phone' => 'hidden',
                'email' => 'required',
                'address' => 'optional',
            ],
        ]);
    });

    it('declares the keys of every nested section the client types', function (string $section, array $keys) {
        expect(array_keys(serializedBusinessSettings()[$section]))->toBe($keys);
    })->with([
        'phone' => ['phone', ['country_code', 'national_number', 'e164']],
        'address' => ['address', ['street', 'city', 'state_id', 'postal_code', 'country_code', 'latitude', 'longitude']],
        'booking page' => ['booking_page', ['accent_color', 'button_shape', 'theme', 'banner_url', 'gallery']],
        'booking policy' => ['booking_policy', [
            'lead_time_minutes',
            'booking_window_minutes',
            'slot_granularity_minutes',
            'cancellation_window_minutes',
            'policy_message',
            'display_on_booking_page',
        ]],
        'contact fields' => ['contact_fields', ['phone', 'email', 'address']],
    ]);

    it('declares the keys of every row in a collection section', function () {
        $serialized = serializedBusinessSettings();

        expect(array_keys($serialized['schedule'][0]))->toBe(['weekday', 'starts_at', 'ends_at'])
            ->and(array_keys($serialized['links'][0]))->toBe(['platform', 'url', 'position'])
            ->and(array_keys($serialized['booking_page']['gallery'][0]))->toBe(['id', 'url']);
    });
});

describe('identity on the wire', function () {
    it('exposes the uuid as the id, never an internal key', function () {
        expect(serializedBusinessSettings()['id'])->toBe(SETTINGS_BUSINESS_ID)
            ->toBeString()
            ->and(is_numeric(serializedBusinessSettings()['id']))->toBeFalse();
    });

    it('sends every neighbour id as a uuid string', function () {
        $serialized = serializedBusinessSettings();

        expect($serialized['industry_id'])->toBe(SETTINGS_INDUSTRY_ID)
            ->and($serialized['address']['state_id'])->toBe(SETTINGS_STATE_ID)
            ->and($serialized['booking_page']['gallery'][0]['id'])->toBe(SETTINGS_IMAGE_ID);
    });

    it('never serializes business_id, at any depth', function () {
        expect(json_encode(serializedBusinessSettings(), JSON_THROW_ON_ERROR))
            ->not->toContain('business_id');
    });

    it('never serializes the booking page identity, which no client asks for', function () {
        expect(serializedBusinessSettings()['booking_page'])->not->toHaveKey('id');
    });
});

describe('what an empty business serializes as', function () {
    it('declares the same key set as a business that filled everything', function () {
        expect(array_keys(serializedBusinessSettings(businessSettingsNothingFiled())))
            ->toBe(array_keys(serializedBusinessSettings()));
    });

    it('sends null rather than dropping the key for every section the business left empty', function () {
        $serialized = serializedBusinessSettings(businessSettingsNothingFiled());

        expect($serialized)->toHaveKeys(['phone', 'address', 'about', 'contact_email', 'logo_url'])
            ->and($serialized['phone'])->toBeNull()
            ->and($serialized['address'])->toBeNull()
            ->and($serialized['about'])->toBeNull()
            ->and($serialized['contact_email'])->toBeNull()
            ->and($serialized['logo_url'])->toBeNull()
            ->and($serialized['booking_page'])->toHaveKey('banner_url')
            ->and($serialized['booking_page']['banner_url'])->toBeNull();
    });

    it('still sends the booking page, which is provisioned rather than optional', function () {
        $serialized = serializedBusinessSettings(businessSettingsNothingFiled());

        expect($serialized['booking_page'])->toBeArray()
            ->and($serialized['booking_page']['accent_color'])->toBe('teal');
    });

    it('still sends the booking policy, which is provisioned rather than optional', function () {
        $serialized = serializedBusinessSettings(businessSettingsNothingFiled());

        expect($serialized['booking_policy'])->toBeArray()
            ->and($serialized['booking_policy']['lead_time_minutes'])->toBe(0)
            ->and($serialized['booking_policy']['slot_granularity_minutes'])->toBe(15)
            ->and($serialized['booking_policy']['display_on_booking_page'])->toBeFalse();
    });

    it('still sends the contact fields, which are provisioned with the booking policy', function () {
        expect(serializedBusinessSettings(businessSettingsNothingFiled())['contact_fields'])->toBe([
            'phone' => 'required',
            'email' => 'optional',
            'address' => 'hidden',
        ]);
    });

    it('sends an unlimited window and a cancellation nobody may use as null, never as zero', function () {
        $serialized = serializedBusinessSettings(businessSettingsNothingFiled());

        expect($serialized['booking_policy'])->toHaveKeys(['booking_window_minutes', 'cancellation_window_minutes'])
            ->and($serialized['booking_policy']['booking_window_minutes'])->toBeNull()
            ->and($serialized['booking_policy']['cancellation_window_minutes'])->toBeNull()
            ->and($serialized['booking_policy']['policy_message'])->toBeNull();
    });

    it('never confuses an unlimited window with a window of zero minutes', function () {
        $serialized = serializedBusinessSettings(businessSettingsData(
            bookingPolicy: businessSettingsBookingPolicy(bookingWindowMinutes: null, cancellationWindowMinutes: 0),
        ));

        expect($serialized['booking_policy']['booking_window_minutes'])->not->toBe(0)
            ->and($serialized['booking_policy']['booking_window_minutes'])->toBeNull()
            ->and($serialized['booking_policy']['cancellation_window_minutes'])->toBe(0);
    });

    it('sends an empty json array, never an object, for a section with no rows', function () {
        $serialized = serializedBusinessSettings(businessSettingsNothingFiled());

        expect($serialized['schedule'])->toBe([])
            ->and($serialized['links'])->toBe([])
            ->and($serialized['booking_page']['gallery'])->toBe([])
            ->and(json_encode($serialized['schedule'], JSON_THROW_ON_ERROR))->toBe('[]')
            ->and(json_encode($serialized['links'], JSON_THROW_ON_ERROR))->toBe('[]')
            ->and(json_encode($serialized['booking_page']['gallery'], JSON_THROW_ON_ERROR))->toBe('[]');
    });

    it('keeps a collection a json array once a row is removed from the middle', function () {
        $serialized = serializedBusinessSettings(businessSettingsData(schedule: array_values(array_filter(
            [
                new BusinessScheduleEntry(weekday: 1, startsAt: '09:00', endsAt: '14:00'),
                new BusinessScheduleEntry(weekday: 2, startsAt: '09:00', endsAt: '14:00'),
            ],
            static fn (BusinessScheduleEntry $entry): bool => $entry->weekday !== 1,
        ))));

        expect(json_encode($serialized['schedule'], JSON_THROW_ON_ERROR))
            ->toBe('[{"weekday":2,"starts_at":"09:00","ends_at":"14:00"}]');
    });
});

describe('the contact fields', function () {
    it('sends each requirement as the string the form request accepts back', function (ContactFieldPreference $preference) {
        $serialized = serializedBusinessSettings(businessSettingsData(
            contactFields: businessSettingsContactFields($preference, $preference, $preference),
        ))['contact_fields'];

        expect($serialized)->toBe([
            'phone' => $preference->value,
            'email' => $preference->value,
            'address' => $preference->value,
        ]);
    })->with(ContactFieldPreference::cases());

    it('keeps each requirement under the field it belongs to', function () {
        $serialized = serializedBusinessSettings(businessSettingsData(contactFields: businessSettingsContactFields(
            phone: ContactFieldPreference::Optional,
            email: ContactFieldPreference::Hidden,
            address: ContactFieldPreference::Required,
        )))['contact_fields'];

        expect($serialized['phone'])->toBe('optional')
            ->and($serialized['email'])->toBe('hidden')
            ->and($serialized['address'])->toBe('required');
    });

    it('sits beside the booking policy rather than inside it', function () {
        $serialized = serializedBusinessSettings();

        expect($serialized)->toHaveKey('contact_fields')
            ->and($serialized['booking_policy'])->not->toHaveKey('contact_fields')
            ->and($serialized['booking_policy'])->not->toHaveKeys(['phone_field', 'email_field', 'address_field']);
    });

    it('encodes the section as a json object the client can index by field', function () {
        expect(json_encode(serializedBusinessSettings()['contact_fields'], JSON_THROW_ON_ERROR))
            ->toBe('{"phone":"hidden","email":"required","address":"optional"}');
    });
});

describe('values that must survive untouched', function () {
    it('keeps unicode and line breaks in the description', function () {
        $about = "Barbería Ñandú\nDesde 2019.";

        expect(serializedBusinessSettings(businessSettingsData(about: $about))['about'])->toBe($about);
    });

    it('keeps the local times of the week as HH:mm strings, never instants', function () {
        $serialized = serializedBusinessSettings(businessSettingsData(schedule: [
            new BusinessScheduleEntry(weekday: 7, startsAt: '09:05', endsAt: '23:59'),
        ]));

        expect($serialized['schedule'][0]['starts_at'])->toBe('09:05')
            ->and($serialized['schedule'][0]['ends_at'])->toBe('23:59')
            ->and($serialized['schedule'][0]['weekday'])->toBe(7);
    });

    it('keeps the coordinates as strings, so no decimal is lost to a float', function () {
        $serialized = serializedBusinessSettings();

        expect($serialized['address']['latitude'])->toBeString()
            ->and($serialized['address']['longitude'])->toBeString();
    });

    it('sends null coordinates for an address nobody pinned', function () {
        $serialized = serializedBusinessSettings(businessSettingsData(
            address: businessSettingsAddress(latitude: null, longitude: null),
        ));

        expect($serialized['address'])->toHaveKeys(['latitude', 'longitude'])
            ->and($serialized['address']['latitude'])->toBeNull()
            ->and($serialized['address']['longitude'])->toBeNull();
    });

    it('sends null city and postal code for an address filed with a street alone', function () {
        $serialized = serializedBusinessSettings(businessSettingsData(
            address: businessSettingsAddress(stateId: null, city: null, postalCode: null),
        ));

        expect(array_keys($serialized['address']))
            ->toBe(['street', 'city', 'state_id', 'postal_code', 'country_code', 'latitude', 'longitude'])
            ->and($serialized['address']['street'])->toBe('Avenida Insurgentes Sur 1602')
            ->and($serialized['address']['city'])->toBeNull()
            ->and($serialized['address']['postal_code'])->toBeNull();
    });

    it('sends a null state for an address in a country with none', function () {
        $serialized = serializedBusinessSettings(businessSettingsData(
            address: businessSettingsAddress(stateId: null),
        ));

        expect($serialized['address'])->toHaveKey('state_id')
            ->and($serialized['address']['state_id'])->toBeNull();
    });

    it('splits the phone into the three parts the form edits', function () {
        $serialized = serializedBusinessSettings(businessSettingsData(phone: PhoneNumbers::american()));

        expect($serialized['phone'])->toBe([
            'country_code' => 'US',
            'national_number' => PhoneNumbers::US_NATIONAL_NUMBER,
            'e164' => PhoneNumbers::US_E164,
        ]);
    });

    it('keeps the links in the order the aggregate ordered them', function () {
        $serialized = serializedBusinessSettings(businessSettingsData(links: [
            new BusinessLinkSnapshot(platform: 'website', url: 'https://mizita.test/ada-salon', position: 0),
            new BusinessLinkSnapshot(platform: 'instagram', url: 'https://instagram.com/ada.salon', position: 10),
        ]));

        expect(array_column($serialized['links'], 'platform'))->toBe(['website', 'instagram'])
            ->and(array_column($serialized['links'], 'position'))->toBe([0, 10]);
    });
});
