<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\PublicBusinessPageData;
use App\Domains\PublicCatalog\Infrastructure\Http\Resources\PublicBusinessPageResource;
use App\Domains\PublicCatalog\ValueObjects\PublicOpenState;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function serializedPublicBusinessPage(?PublicBusinessPageData $page = null): array
{
    return (array) PublicBusinessPageResource::make($page ?? PublicCatalogFixtures::page())
        ->response()
        ->getData(true)['data'];
}

/**
 * @param  array<array-key, mixed>  $payload
 * @return list<string>
 */
function publicPageKeysAtEveryDepth(array $payload): array
{
    $keys = [];

    foreach ($payload as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        if (is_array($value)) {
            $keys = [...$keys, ...publicPageKeysAtEveryDepth($value)];
        }
    }

    return array_values(array_unique($keys));
}

/**
 * @param  array<array-key, mixed>  $payload
 * @return list<array{key: string, value: mixed}>
 */
function publicPageIdentifiersAtEveryDepth(array $payload): array
{
    $identifiers = [];

    foreach ($payload as $key => $value) {
        if (is_array($value)) {
            $identifiers = [...$identifiers, ...publicPageIdentifiersAtEveryDepth($value)];

            continue;
        }

        if (is_string($key) && ($key === 'id' || str_ends_with($key, '_id'))) {
            $identifiers[] = ['key' => $key, 'value' => $value];
        }
    }

    return $identifiers;
}

dataset('keys a visitor may never see', [
    'email',
    'contact_email',
    'industry_id',
    'state_id',
    'role',
    'active',
    'buffer_minutes',
    'booking_url',
    'color',
    'created_at',
]);

describe('what a visitor is allowed to see', function () {
    it('carries no key the business keeps to itself, at any depth', function (string $forbidden) {
        expect(publicPageKeysAtEveryDepth(serializedPublicBusinessPage()))->not->toContain($forbidden);
    })->with('keys a visitor may never see');

    it('carries none of them for an empty business either, where a null would still leak the field', function (string $forbidden) {
        expect(publicPageKeysAtEveryDepth(serializedPublicBusinessPage(PublicCatalogFixtures::emptyPage())))
            ->not->toContain($forbidden);
    })->with('keys a visitor may never see');

    it('declares the whole key set at every depth, so a new field has to be added deliberately', function () {
        expect(publicPageKeysAtEveryDepth(serializedPublicBusinessPage()))->toBe([
            'id',
            'name',
            'slug',
            'about',
            'timezone',
            'currency_code',
            'logo_url',
            'brand',
            'accent_color',
            'button_shape',
            'theme',
            'banner_url',
            'gallery',
            'url',
            'schedule',
            'weekday',
            'starts_at',
            'ends_at',
            'open_state',
            'open',
            'closes_at',
            'opens_on_weekday',
            'opens_at',
            'last_bookable_date',
            'services',
            'description',
            'duration_minutes',
            'price',
            'image_url',
            'staff_ids',
            'team',
            'location',
            'street',
            'city',
            'state',
            'postal_code',
            'country_code',
            'latitude',
            'longitude',
            'contact',
            'phone',
            'links',
            'platform',
            'booking_policy',
            'policy_message',
        ]);
    });

    it('declares the same key set for a business that filled nothing in', function () {
        $filled = publicPageKeysAtEveryDepth(serializedPublicBusinessPage());
        $empty = publicPageKeysAtEveryDepth(serializedPublicBusinessPage(PublicCatalogFixtures::emptyPage()));

        expect(array_values(array_diff($empty, $filled)))->toBe([]);
    });
});

describe('the client contract', function () {
    it('serializes exactly the top level keys the booking page reads', function () {
        expect(array_keys(serializedPublicBusinessPage()))->toBe([
            'id',
            'name',
            'slug',
            'about',
            'timezone',
            'currency_code',
            'logo_url',
            'brand',
            'schedule',
            'open_state',
            'last_bookable_date',
            'services',
            'team',
            'location',
            'contact',
            'booking_policy',
        ]);
    });

    it('wraps the payload in the data envelope', function () {
        expect(PublicBusinessPageResource::make(PublicCatalogFixtures::page())->response()->getData(true))
            ->toHaveKey('data');
    });

    it('declares the keys of every nested section', function (string $section, array $keys) {
        expect(array_keys(serializedPublicBusinessPage()[$section]))->toBe($keys);
    })->with([
        'brand' => ['brand', ['accent_color', 'button_shape', 'theme', 'banner_url', 'gallery']],
        'location' => ['location', ['street', 'city', 'state', 'postal_code', 'country_code', 'latitude', 'longitude']],
        'contact' => ['contact', ['phone', 'links']],
    ]);

    it('declares the keys of every row in a collection section', function () {
        $serialized = serializedPublicBusinessPage();

        expect(array_keys($serialized['schedule'][0]))->toBe(['weekday', 'starts_at', 'ends_at'])
            ->and(array_keys($serialized['services'][0]))
            ->toBe(['id', 'name', 'slug', 'description', 'duration_minutes', 'price', 'image_url', 'staff_ids'])
            ->and(array_keys($serialized['team'][0]))->toBe(['id', 'name'])
            ->and(array_keys($serialized['brand']['gallery'][0]))->toBe(['id', 'url'])
            ->and(array_keys($serialized['contact']['links'][0]))->toBe(['platform', 'url']);
    });

    it('serializes the whole page a visitor reads', function () {
        expect(serializedPublicBusinessPage())->toBe([
            'id' => PublicCatalogFixtures::BUSINESS_ID,
            'name' => PublicCatalogFixtures::NAME,
            'slug' => PublicCatalogFixtures::SLUG,
            'about' => 'Cortes y color desde 2019.',
            'timezone' => PublicCatalogFixtures::TIMEZONE,
            'currency_code' => PublicCatalogFixtures::CURRENCY_CODE,
            'logo_url' => PublicCatalogFixtures::LOGO_URL,
            'brand' => [
                'accent_color' => 'teal',
                'button_shape' => 'rounded',
                'theme' => 'dark',
                'banner_url' => PublicCatalogFixtures::BANNER_URL,
                'gallery' => [
                    ['id' => PublicCatalogFixtures::IMAGE_ID, 'url' => PublicCatalogFixtures::GALLERY_URL],
                ],
            ],
            'schedule' => [
                ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '14:00'],
            ],
            'open_state' => [
                'open' => true,
                'closes_at' => PublicCatalogFixtures::CLOSES_AT,
                'opens_on_weekday' => null,
                'opens_at' => null,
            ],
            'last_bookable_date' => PublicCatalogFixtures::LAST_BOOKABLE_DATE,
            'services' => [
                [
                    'id' => PublicCatalogFixtures::SERVICE_ID,
                    'name' => 'Corte de pelo',
                    'slug' => 'corte-de-pelo',
                    'description' => 'Incluye lavado.',
                    'duration_minutes' => 45,
                    'price' => '250.00',
                    'image_url' => PublicCatalogFixtures::SERVICE_IMAGE_URL,
                    'staff_ids' => [PublicCatalogFixtures::TEAM_MEMBER_ID],
                ],
            ],
            'team' => [
                ['id' => PublicCatalogFixtures::TEAM_MEMBER_ID, 'name' => 'Ada Lovelace'],
            ],
            'location' => [
                'street' => 'Avenida Insurgentes Sur 1602',
                'city' => 'Ciudad de México',
                'state' => 'Ciudad de México',
                'postal_code' => '03940',
                'country_code' => 'MX',
                'latitude' => '19.3627888',
                'longitude' => '-99.1768069',
            ],
            'contact' => [
                'phone' => '+525512345678',
                'links' => [
                    ['platform' => 'instagram', 'url' => PublicCatalogFixtures::INSTAGRAM_URL],
                ],
            ],
            'booking_policy' => ['policy_message' => PublicCatalogFixtures::POLICY_MESSAGE],
        ]);
    });
});

describe('the booking policy a visitor is shown', function () {
    it('carries the message the business chose to display', function () {
        expect(serializedPublicBusinessPage()['booking_policy'])
            ->toBe(['policy_message' => PublicCatalogFixtures::POLICY_MESSAGE]);
    });

    it('leaves the booking policy key out altogether when the business displays none', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::page(bookingPolicy: null));

        expect($serialized)->not->toHaveKey('booking_policy')
            ->and(array_keys($serialized))->not->toContain('booking_policy')
            ->and(json_encode($serialized, JSON_THROW_ON_ERROR))->not->toContain('booking_policy');
    });

    it('sends no null placeholder for a business that displays no policy', function () {
        expect(publicPageKeysAtEveryDepth(serializedPublicBusinessPage(PublicCatalogFixtures::emptyPage())))
            ->not->toContain('booking_policy')
            ->and(publicPageKeysAtEveryDepth(serializedPublicBusinessPage(PublicCatalogFixtures::emptyPage())))
            ->not->toContain('policy_message');
    });

    it('carries only the message, never the minutes the business schedules by', function () {
        $keys = publicPageKeysAtEveryDepth(serializedPublicBusinessPage());

        expect(array_keys(serializedPublicBusinessPage()['booking_policy']))->toBe(['policy_message'])
            ->and($keys)->not->toContain('lead_time_minutes')
            ->and($keys)->not->toContain('booking_window_minutes')
            ->and($keys)->not->toContain('slot_granularity_minutes')
            ->and($keys)->not->toContain('cancellation_window_minutes')
            ->and($keys)->not->toContain('display_on_booking_page');
    });
});

describe('whether the doors are open right now', function () {
    it('declares the keys the booking page reads off the open state', function () {
        expect(array_keys(serializedPublicBusinessPage()['open_state']))
            ->toBe(['open', 'closes_at', 'opens_on_weekday', 'opens_at']);
    });

    it('tells a visitor a business is open and when it closes', function () {
        expect(serializedPublicBusinessPage()['open_state'])->toBe([
            'open' => true,
            'closes_at' => PublicCatalogFixtures::CLOSES_AT,
            'opens_on_weekday' => null,
            'opens_at' => null,
        ]);
    });

    it('tells a visitor a closed business when it opens next', function () {
        expect(serializedPublicBusinessPage(PublicCatalogFixtures::page(
            openState: PublicCatalogFixtures::closedState(),
        ))['open_state'])->toBe([
            'open' => false,
            'closes_at' => null,
            'opens_on_weekday' => PublicCatalogFixtures::OPENS_ON_WEEKDAY,
            'opens_at' => PublicCatalogFixtures::OPENS_AT,
        ]);
    });

    it('promises no next opening for a business that publishes no hours at all', function () {
        expect(serializedPublicBusinessPage(PublicCatalogFixtures::emptyPage())['open_state'])->toBe([
            'open' => false,
            'closes_at' => null,
            'opens_on_weekday' => null,
            'opens_at' => null,
        ]);
    });

    it('never lets the open flag disagree with the payload it was derived from', function (PublicOpenState $state) {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::page(openState: $state))['open_state'];

        expect($serialized['open'])->toBe($serialized['closes_at'] !== null)->toBeBool();
    })->with([
        'open until closing time' => [fn () => PublicOpenState::openUntil('18:00')],
        'open until midnight' => [fn () => PublicOpenState::openUntil('23:59')],
        'closed until Monday' => [fn () => PublicOpenState::closedUntil(1, '09:00')],
        'closed until Sunday' => [fn () => PublicOpenState::closedUntil(7, '10:30')],
        'closed indefinitely' => [fn () => PublicOpenState::closedIndefinitely()],
    ]);

    it('sends the local times of the open state as HH:mm strings, never instants', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::page(
            openState: PublicOpenState::closedUntil(7, '10:30'),
        ))['open_state'];

        expect($serialized['opens_at'])->toBe('10:30')
            ->and($serialized['opens_on_weekday'])->toBe(7)->toBeInt();
    });
});

describe('the last date a visitor may still book', function () {
    it('is always present, so the calendar always has a bound to render', function () {
        expect(serializedPublicBusinessPage())->toHaveKey('last_bookable_date')
            ->and(serializedPublicBusinessPage()['last_bookable_date'])
            ->toBe(PublicCatalogFixtures::LAST_BOOKABLE_DATE);
    });

    it('is never null, not even for a business that filled nothing in', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::emptyPage());

        expect($serialized)->toHaveKey('last_bookable_date')
            ->and($serialized['last_bookable_date'])->not->toBeNull()
            ->and($serialized['last_bookable_date'])->toBeString();
    });

    it('is a calendar date, never an instant', function () {
        expect(serializedPublicBusinessPage(PublicCatalogFixtures::page(lastBookableDate: '2026-12-31')))
            ->toMatchArray(['last_bookable_date' => '2026-12-31']);
    });
});

describe('the staff a service may be booked with', function () {
    it('sends every staff id as a uuid string, never an internal key', function () {
        $staffIds = serializedPublicBusinessPage(PublicCatalogFixtures::page(
            services: [PublicCatalogFixtures::service(staffIds: [
                PublicCatalogFixtures::TEAM_MEMBER_ID,
                PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
            ])],
        ))['services'][0]['staff_ids'];

        expect($staffIds)->toBe([
            PublicCatalogFixtures::TEAM_MEMBER_ID,
            PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
        ])->and(array_filter($staffIds, is_numeric(...)))->toBe([]);
    });

    it('sends an empty json array, never an object, for a service nobody performs', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::page(
            services: [PublicCatalogFixtures::service(staffIds: [])],
        ));

        expect(json_encode($serialized['services'][0]['staff_ids'], JSON_THROW_ON_ERROR))->toBe('[]');
    });
});

describe('identity on the wire', function () {
    it('sends every id as a uuid string, never an internal key', function () {
        $identifiers = publicPageIdentifiersAtEveryDepth(serializedPublicBusinessPage());
        $values = array_column($identifiers, 'value');

        expect(array_unique(array_column($identifiers, 'key')))->toBe(['id'])
            ->and($values)->toBe([
                PublicCatalogFixtures::BUSINESS_ID,
                PublicCatalogFixtures::IMAGE_ID,
                PublicCatalogFixtures::SERVICE_ID,
                PublicCatalogFixtures::TEAM_MEMBER_ID,
            ])
            ->and(array_filter($values, is_numeric(...)))->toBe([]);
    });

    it('never serializes business_id, at any depth', function () {
        expect(publicPageKeysAtEveryDepth(serializedPublicBusinessPage()))->not->toContain('business_id')
            ->and(json_encode(serializedPublicBusinessPage(), JSON_THROW_ON_ERROR))->not->toContain('business_id');
    });
});

describe('what an empty business serializes as', function () {
    it('sends null rather than dropping the key for every section left empty', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::emptyPage());

        expect($serialized)->toHaveKeys(['about', 'logo_url', 'location'])
            ->and($serialized['about'])->toBeNull()
            ->and($serialized['logo_url'])->toBeNull()
            ->and($serialized['location'])->toBeNull()
            ->and($serialized['brand']['banner_url'])->toBeNull()
            ->and($serialized['contact']['phone'])->toBeNull();
    });

    it('sends null city and postal code for a business that filed a street alone', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::page(
            location: PublicCatalogFixtures::location(city: null, state: null, postalCode: null),
        ));

        expect(array_keys($serialized['location']))
            ->toBe(['street', 'city', 'state', 'postal_code', 'country_code', 'latitude', 'longitude'])
            ->and($serialized['location']['street'])->toBe('Avenida Insurgentes Sur 1602')
            ->and($serialized['location']['city'])->toBeNull()
            ->and($serialized['location']['state'])->toBeNull()
            ->and($serialized['location']['postal_code'])->toBeNull();
    });

    it('sends an empty json array, never an object, for a section with no rows', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::emptyPage());

        expect(json_encode($serialized['schedule'], JSON_THROW_ON_ERROR))->toBe('[]')
            ->and(json_encode($serialized['services'], JSON_THROW_ON_ERROR))->toBe('[]')
            ->and(json_encode($serialized['team'], JSON_THROW_ON_ERROR))->toBe('[]')
            ->and(json_encode($serialized['brand']['gallery'], JSON_THROW_ON_ERROR))->toBe('[]')
            ->and(json_encode($serialized['contact']['links'], JSON_THROW_ON_ERROR))->toBe('[]');
    });

    it('still styles the page, because a visitor always needs something to render', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::emptyPage());

        expect($serialized['brand']['accent_color'])->toBe('teal')
            ->and($serialized['brand']['theme'])->toBe('dark');
    });
});

describe('values that must survive untouched', function () {
    it('keeps unicode and line breaks in the description', function () {
        $about = "Barbería Ñandú\nDesde 2019.";

        expect(serializedPublicBusinessPage(PublicCatalogFixtures::page(
            profile: PublicCatalogFixtures::profile(about: $about),
        ))['about'])->toBe($about);
    });

    it('keeps the price as a string, so no cent is lost to a float', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::page(
            services: [PublicCatalogFixtures::service(price: '1250.50')],
        ));

        expect($serialized['services'][0]['price'])->toBe('1250.50')->toBeString();
    });

    it('keeps the local times of the week as HH:mm strings, never instants', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::page(
            schedule: [PublicCatalogFixtures::scheduleEntry(weekday: 7, startsAt: '09:05', endsAt: '23:59')],
        ));

        expect($serialized['schedule'][0])->toBe(['weekday' => 7, 'starts_at' => '09:05', 'ends_at' => '23:59']);
    });

    it('keeps the order of every collection the page assembled', function () {
        $serialized = serializedPublicBusinessPage(PublicCatalogFixtures::page(
            services: [
                PublicCatalogFixtures::service(id: PublicCatalogFixtures::SECOND_SERVICE_ID, name: 'Barba', slug: 'barba'),
                PublicCatalogFixtures::service(),
            ],
        ));

        expect(array_column($serialized['services'], 'slug'))->toBe(['barba', 'corte-de-pelo']);
    });
});
