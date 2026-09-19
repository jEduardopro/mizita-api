<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\AppearanceInput;
use App\Domains\Businesses\Application\Dtos\BookingPolicyInput;
use App\Domains\Businesses\Application\Dtos\BrandDetailsInput;
use App\Domains\Businesses\Application\Dtos\ContactInput;
use App\Domains\Businesses\Application\Dtos\LinksInput;
use App\Domains\Businesses\Application\Dtos\LocationInput;
use App\Domains\Businesses\Application\Dtos\ScheduleInput;
use App\Domains\Businesses\Application\Dtos\UpdateBusinessSettingsInput;
use App\Domains\Businesses\Exceptions\IncompleteBookingPolicy;
use App\Domains\Businesses\Exceptions\InvalidBusinessContactEmail;
use App\Domains\Businesses\Exceptions\InvalidBusinessCoordinates;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\Businesses\SettingsFixtures;
use Tests\Support\PhoneNumbers;

describe('reading a submitted payload', function () {
    it('builds every section a full submission carries', function () {
        $input = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload());

        expect($input->brand)->toBeInstanceOf(BrandDetailsInput::class)
            ->and($input->appearance)->toBeInstanceOf(AppearanceInput::class)
            ->and($input->contact)->toBeInstanceOf(ContactInput::class)
            ->and($input->location)->toBeInstanceOf(LocationInput::class)
            ->and($input->schedule)->toBeInstanceOf(ScheduleInput::class)
            ->and($input->links)->toBeInstanceOf(LinksInput::class);
    });

    it('reads the brand under the keys the client sends', function () {
        $brand = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload())->brand;

        expect($brand?->name)->toBe(OnboardingFixtures::NAME)
            ->and($brand?->slug)->toBe(OnboardingFixtures::SLUG)
            ->and($brand?->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($brand?->about)->toBe(SettingsFixtures::ABOUT);
    });

    it('reads the location under the keys the client sends', function () {
        $location = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload())->location;

        expect($location?->street)->toBe(SettingsFixtures::STREET)
            ->and($location?->city)->toBe(SettingsFixtures::CITY)
            ->and($location?->stateId)->toBe(SettingsFixtures::STATE_ID)
            ->and($location?->postalCode)->toBe(SettingsFixtures::POSTAL_CODE)
            ->and($location?->countryCode)->toBe('MX')
            ->and($location?->latitude)->toBe(SettingsFixtures::LATITUDE)
            ->and($location?->longitude)->toBe(SettingsFixtures::LONGITUDE)
            ->and($location?->currencyCode)->toBe('MXN')
            ->and($location?->timezone)->toBe(OnboardingFixtures::TIMEZONE);
    });

    it('reads the appearance under the keys the client sends', function () {
        $appearance = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload())->appearance;

        expect($appearance?->accentColor)->toBe('teal')
            ->and($appearance?->buttonShape)->toBe('rounded')
            ->and($appearance?->theme)->toBe('dark');
    });

    it('reads the contact details, phone included', function () {
        $contact = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload())->contact;

        expect($contact?->contactEmail)->toBe(SettingsFixtures::CONTACT_EMAIL)
            ->and($contact?->phone?->countryCode)->toBe('MX')
            ->and($contact?->phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER);
    });

    it('numbers the links by the order they arrived in, so the client never sends a position', function () {
        $links = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload([
            'links' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/barberia'],
                ['platform' => 'facebook', 'url' => 'https://facebook.com/barberia'],
                ['platform' => 'website', 'url' => 'https://barberia.com'],
            ],
        ]))->links?->links;

        expect($links)->toHaveCount(3)
            ->and($links[0]->platform)->toBe('instagram')
            ->and($links[0]->position)->toBe(0)
            ->and($links[1]->platform)->toBe('facebook')
            ->and($links[1]->position)->toBe(1)
            ->and($links[2]->position)->toBe(2);
    });

    it('reads a weekday the client sent as a string, because a form field is text', function () {
        $entries = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload([
            'schedule' => [['weekday' => '3', 'starts_at' => '09:00', 'ends_at' => '14:00']],
        ]))->schedule?->entries;

        expect($entries[0]->weekday)->toBe(3)
            ->and($entries[0]->startsAt)->toBe('09:00')
            ->and($entries[0]->endsAt)->toBe('14:00');
    });

    it('keeps several intervals on one weekday, because a business may split its shift', function () {
        $entries = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload([
            'schedule' => [
                ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '14:00'],
                ['weekday' => 1, 'starts_at' => '16:00', 'ends_at' => '20:00'],
            ],
        ]))->schedule?->entries;

        expect($entries)->toHaveCount(2)
            ->and($entries[1]->startsAt)->toBe('16:00');
    });

    it('reads a closed week as a schedule with no intervals, not as a section left out', function () {
        $input = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload(['schedule' => []]));

        expect($input->schedule)->toBeInstanceOf(ScheduleInput::class)
            ->and($input->schedule?->entries)->toBe([]);
    });

    it('reads an emptied link list as a list to clear, not as a section left out', function () {
        $input = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload(['links' => []]));

        expect($input->links)->toBeInstanceOf(LinksInput::class)
            ->and($input->links?->links)->toBe([]);
    });
});

describe('the booking policy section', function () {
    it('reads the booking policy under the keys the client sends', function () {
        $bookingPolicy = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload([
            'booking_policy' => SettingsFixtures::bookingPolicySection(),
        ]))->bookingPolicy;

        expect($bookingPolicy)->toBeInstanceOf(BookingPolicyInput::class)
            ->and($bookingPolicy?->leadTimeMinutes)->toBe(SettingsFixtures::LEAD_TIME_MINUTES)
            ->and($bookingPolicy?->bookingWindowMinutes)->toBe(SettingsFixtures::BOOKING_WINDOW_MINUTES)
            ->and($bookingPolicy?->slotGranularityMinutes)->toBe(SettingsFixtures::SLOT_GRANULARITY_MINUTES)
            ->and($bookingPolicy?->cancellationWindowMinutes)->toBe(SettingsFixtures::CANCELLATION_WINDOW_MINUTES)
            ->and($bookingPolicy?->policyMessage)->toBe(SettingsFixtures::POLICY_MESSAGE)
            ->and($bookingPolicy?->displayOnBookingPage)->toBeTrue();
    });

    it('reads the minutes the client sent as strings, because a form field is text', function () {
        $bookingPolicy = UpdateBusinessSettingsInput::fromRequest([
            'booking_policy' => [
                'lead_time_minutes' => '60',
                'booking_window_minutes' => '43200',
                'slot_granularity_minutes' => '30',
                'cancellation_window_minutes' => '240',
            ],
        ])->bookingPolicy;

        expect($bookingPolicy?->leadTimeMinutes)->toBe(60)
            ->and($bookingPolicy?->bookingWindowMinutes)->toBe(43200)
            ->and($bookingPolicy?->slotGranularityMinutes)->toBe(30)
            ->and($bookingPolicy?->cancellationWindowMinutes)->toBe(240);
    });

    it('reads a window nobody bounded as null, never as zero', function (mixed $submitted) {
        $bookingPolicy = UpdateBusinessSettingsInput::fromRequest([
            'booking_policy' => [
                'booking_window_minutes' => $submitted,
                'cancellation_window_minutes' => $submitted,
            ],
        ])->bookingPolicy;

        expect($bookingPolicy?->bookingWindowMinutes)->toBeNull()
            ->and($bookingPolicy?->cancellationWindowMinutes)->toBeNull();
    })->with([
        'null' => null,
        'an empty string' => '',
        'a word' => 'unlimited',
    ]);

    it('survives a booking policy with every key missing', function () {
        $bookingPolicy = UpdateBusinessSettingsInput::fromRequest(['booking_policy' => []])->bookingPolicy;

        expect($bookingPolicy?->leadTimeMinutes)->toBe(0)
            ->and($bookingPolicy?->bookingWindowMinutes)->toBeNull()
            ->and($bookingPolicy?->slotGranularityMinutes)->toBe(0)
            ->and($bookingPolicy?->cancellationWindowMinutes)->toBeNull()
            ->and($bookingPolicy?->policyMessage)->toBeNull()
            ->and($bookingPolicy?->displayOnBookingPage)->toBeFalse();
    });

    it('reads a blank policy message as nothing at all', function (mixed $blank) {
        expect(UpdateBusinessSettingsInput::fromRequest([
            'booking_policy' => ['policy_message' => $blank],
        ])->bookingPolicy?->policyMessage)->toBeNull();
    })->with([
        'an empty string' => '',
        'spaces' => '   ',
        'null' => null,
    ]);

    it('reads the toggle however the client spelled a boolean', function (mixed $submitted, bool $expected) {
        expect(UpdateBusinessSettingsInput::fromRequest([
            'booking_policy' => ['display_on_booking_page' => $submitted],
        ])->bookingPolicy?->displayOnBookingPage)->toBe($expected);
    })->with([
        'true' => [true, true],
        'the string true' => ['true', true],
        'one' => [1, true],
        'false' => [false, false],
        'the string false' => ['false', false],
        'zero' => [0, false],
        'null' => [null, false],
    ]);

    it('treats a booking policy it cannot read as one the client never sent', function (mixed $section) {
        expect(UpdateBusinessSettingsInput::fromRequest(['booking_policy' => $section])->bookingPolicy)->toBeNull();
    })->with([
        'null' => null,
        'a string' => 'nonsense',
        'a number' => 7,
    ]);

    it('accepts a booking policy that carries every key the section is made of', function () {
        $input = UpdateBusinessSettingsInput::fromRequest([
            'booking_policy' => SettingsFixtures::bookingPolicySection(),
        ]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a booking policy submitted without one of its keys', function (string $absent) {
        $section = SettingsFixtures::bookingPolicySection();
        unset($section[$absent]);

        $input = UpdateBusinessSettingsInput::fromRequest(['booking_policy' => $section]);

        try {
            $input->validate();
            $thrown = null;
        } catch (Throwable $refusal) {
            $thrown = $refusal;
        }

        expect($thrown)->toBeInstanceOf(IncompleteBookingPolicy::class)
            ->and($thrown->errorCode())->toBe('incomplete_booking_policy')
            ->and($thrown->kind())->toBe(DomainFailureKind::Invalid)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class);
    })->with([
        'lead time' => 'lead_time_minutes',
        'booking window' => 'booking_window_minutes',
        'slot granularity' => 'slot_granularity_minutes',
        'cancellation window' => 'cancellation_window_minutes',
        'policy message' => 'policy_message',
        'display toggle' => 'display_on_booking_page',
    ]);

    it('refuses a booking policy submitted as an empty section', function () {
        expect(fn () => UpdateBusinessSettingsInput::fromRequest(['booking_policy' => []])->validate())
            ->toThrow(IncompleteBookingPolicy::class);
    });

    it('names every key the section was missing, not only the first', function () {
        $input = UpdateBusinessSettingsInput::fromRequest([
            'booking_policy' => ['lead_time_minutes' => 30],
        ]);

        try {
            $input->validate();
            $message = '';
        } catch (IncompleteBookingPolicy $refusal) {
            $message = $refusal->getMessage();
        }

        expect($message)->toContain('booking_window_minutes')
            ->and($message)->toContain('slot_granularity_minutes')
            ->and($message)->toContain('cancellation_window_minutes')
            ->and($message)->toContain('policy_message')
            ->and($message)->toContain('display_on_booking_page')
            ->and($message)->not->toContain('lead_time_minutes');
    });

    it('accepts a key the client deliberately emptied, because null is an answer', function (string $key) {
        $input = UpdateBusinessSettingsInput::fromRequest([
            'booking_policy' => [...SettingsFixtures::bookingPolicySection(), $key => null],
        ]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    })->with([
        'no booking window' => 'booking_window_minutes',
        'no cancellation window' => 'cancellation_window_minutes',
        'no policy message' => 'policy_message',
    ]);

    it('reads an emptied optional key as null rather than as a number', function () {
        $policy = UpdateBusinessSettingsInput::fromRequest([
            'booking_policy' => [
                ...SettingsFixtures::bookingPolicySection(),
                'booking_window_minutes' => null,
                'cancellation_window_minutes' => null,
                'policy_message' => null,
            ],
        ])->bookingPolicy;

        expect($policy)->toBeInstanceOf(BookingPolicyInput::class)
            ->and($policy?->bookingWindowMinutes)->toBeNull()
            ->and($policy?->cancellationWindowMinutes)->toBeNull()
            ->and($policy?->policyMessage)->toBeNull();
    });

    it('leaves the booking policy values themselves to the domain that owns them', function () {
        $input = UpdateBusinessSettingsInput::fromRequest([
            'booking_policy' => [
                ...SettingsFixtures::bookingPolicySection(),
                'lead_time_minutes' => -1,
                'slot_granularity_minutes' => 7,
            ],
        ]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });
});

describe('patching one section at a time', function () {
    it('leaves out every section the client did not send', function () {
        $input = UpdateBusinessSettingsInput::fromRequest([
            'brand' => [
                'name' => OnboardingFixtures::NAME,
                'slug' => OnboardingFixtures::SLUG,
                'industry_id' => OnboardingFixtures::INDUSTRY_ID,
            ],
        ]);

        expect($input->brand)->toBeInstanceOf(BrandDetailsInput::class)
            ->and($input->appearance)->toBeNull()
            ->and($input->contact)->toBeNull()
            ->and($input->location)->toBeNull()
            ->and($input->schedule)->toBeNull()
            ->and($input->links)->toBeNull()
            ->and($input->bookingPolicy)->toBeNull();
    });

    it('builds an input that changes nothing out of an empty payload', function () {
        $input = UpdateBusinessSettingsInput::fromRequest([]);

        expect($input->brand)->toBeNull()
            ->and($input->appearance)->toBeNull()
            ->and($input->contact)->toBeNull()
            ->and($input->location)->toBeNull()
            ->and($input->schedule)->toBeNull()
            ->and($input->links)->toBeNull()
            ->and($input->bookingPolicy)->toBeNull()
            ->and(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('carries no section at all when built by hand with nothing', function () {
        $input = new UpdateBusinessSettingsInput;

        expect($input->brand)->toBeNull()
            ->and($input->links)->toBeNull();
    });

    it('treats a section it cannot read as one the client never sent', function (mixed $section) {
        expect(UpdateBusinessSettingsInput::fromRequest(['brand' => $section])->brand)->toBeNull();
    })->with([
        'null' => null,
        'a string' => 'nonsense',
        'a number' => 7,
        'a boolean' => true,
    ]);
});

describe('reading defensively, for the caller who never met a form request', function () {
    it('survives a brand with every key missing', function () {
        $brand = UpdateBusinessSettingsInput::fromRequest(['brand' => []])->brand;

        expect($brand?->name)->toBe('')
            ->and($brand?->slug)->toBe('')
            ->and($brand?->industryId)->toBe('')
            ->and($brand?->about)->toBeNull();
    });

    it('survives a location with every key missing', function () {
        $location = UpdateBusinessSettingsInput::fromRequest(['location' => []])->location;

        expect($location?->street)->toBe('')
            ->and($location?->city)->toBeNull()
            ->and($location?->stateId)->toBeNull()
            ->and($location?->postalCode)->toBeNull()
            ->and($location?->latitude)->toBeNull()
            ->and($location?->longitude)->toBeNull()
            ->and($location?->currencyCode)->toBe('');
    });

    it('reads a blank city and a blank postal code as nothing at all, never as an empty string', function (mixed $blank) {
        $location = UpdateBusinessSettingsInput::fromRequest([
            'location' => ['city' => $blank, 'postal_code' => $blank],
        ])->location;

        expect($location?->city)->toBeNull()
            ->and($location?->postalCode)->toBeNull();
    })->with([
        'an empty string' => '',
        'spaces' => '   ',
        'a tab' => "\t",
        'null' => null,
    ]);

    it('keeps the street an empty string, because a blank street is what files no address', function () {
        $location = UpdateBusinessSettingsInput::fromRequest(['location' => ['street' => '   ']])->location;

        expect($location?->street)->toBe('   ')
            ->and($location?->city)->toBeNull();
    });

    it('turns a value of the wrong type into the empty one, rather than a php error', function () {
        $brand = UpdateBusinessSettingsInput::fromRequest([
            'brand' => ['name' => ['Barbería'], 'slug' => 7, 'industry_id' => null, 'about' => false],
        ])->brand;

        expect($brand?->name)->toBe('')
            ->and($brand?->slug)->toBe('')
            ->and($brand?->industryId)->toBe('')
            ->and($brand?->about)->toBeNull();
    });

    it('reads a blank optional as nothing at all', function () {
        $input = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload([
            'brand' => [
                'name' => OnboardingFixtures::NAME,
                'slug' => OnboardingFixtures::SLUG,
                'industry_id' => OnboardingFixtures::INDUSTRY_ID,
                'about' => '   ',
            ],
            'location' => ['latitude' => '', 'longitude' => '   '],
        ]));

        expect($input->brand?->about)->toBeNull()
            ->and($input->location?->latitude)->toBeNull()
            ->and($input->location?->longitude)->toBeNull();
    });

    it('reads a business that publishes no phone', function (mixed $phone) {
        expect(UpdateBusinessSettingsInput::fromRequest([
            'contact' => ['contact_email' => SettingsFixtures::CONTACT_EMAIL, 'phone' => $phone],
        ])->contact?->phone)->toBeNull();
    })->with([
        'no key at all' => null,
        'an empty array' => [[]],
        'a string' => 'nonsense',
    ]);

    it('survives a schedule entry that is not an array', function () {
        $entries = UpdateBusinessSettingsInput::fromRequest(['schedule' => ['nonsense']])->schedule?->entries;

        expect($entries)->toHaveCount(1)
            ->and($entries[0]->weekday)->toBe(0)
            ->and($entries[0]->startsAt)->toBe('');
    });

    it('survives a link that is not an array', function () {
        $links = UpdateBusinessSettingsInput::fromRequest(['links' => [7]])->links?->links;

        expect($links)->toHaveCount(1)
            ->and($links[0]->platform)->toBe('')
            ->and($links[0]->url)->toBe('');
    });
});

describe('validating', function () {
    it('accepts a full submission the form request would have let through', function () {
        expect(fn () => SettingsFixtures::everything()->validate())->not->toThrow(Throwable::class);
    });

    it('delegates to the brand, which owns the rules about a name', function () {
        expect(fn () => (new UpdateBusinessSettingsInput(brand: SettingsFixtures::brand(name: '  ')))->validate())
            ->toThrow(InvalidBusinessName::class);
    });

    it('delegates to the contact details, which own the rules about an address', function () {
        expect(fn () => (new UpdateBusinessSettingsInput(contact: SettingsFixtures::contact(contactEmail: 'nope')))->validate())
            ->toThrow(InvalidBusinessContactEmail::class);
    });

    it('delegates to the location, which owns the rules about a coordinate', function () {
        expect(fn () => (new UpdateBusinessSettingsInput(location: SettingsFixtures::location(latitude: 'north')))->validate())
            ->toThrow(InvalidBusinessCoordinates::class);
    });

    it('skips a section the client left out rather than refusing the submission', function () {
        expect(fn () => (new UpdateBusinessSettingsInput(appearance: SettingsFixtures::appearance()))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('names the brand first, then the contact, then the location', function () {
        expect(fn () => (new UpdateBusinessSettingsInput(
            brand: SettingsFixtures::brand(name: ''),
            contact: SettingsFixtures::contact(contactEmail: 'nope'),
            location: SettingsFixtures::location(latitude: 'north'),
        ))->validate())->toThrow(InvalidBusinessName::class);

        expect(fn () => (new UpdateBusinessSettingsInput(
            contact: SettingsFixtures::contact(contactEmail: 'nope'),
            location: SettingsFixtures::location(latitude: 'north'),
        ))->validate())->toThrow(InvalidBusinessContactEmail::class);
    });

    it('leaves the appearance, the schedule and the links to the ports that own their vocabulary', function (string $section) {
        expect(method_exists($section, 'validate'))->toBeFalse();
    })->with([
        'the appearance' => AppearanceInput::class,
        'the schedule' => ScheduleInput::class,
        'the links' => LinksInput::class,
    ]);

    it('refuses nothing about an appearance, a schedule or a link the client made up', function () {
        $input = UpdateBusinessSettingsInput::fromRequest(SettingsFixtures::payload([
            'appearance' => ['accent_color' => 'chartreuse', 'button_shape' => 'blob', 'theme' => 'neon'],
            'schedule' => [['weekday' => 99, 'starts_at' => 'lunchtime', 'ends_at' => 'sundown']],
            'links' => [['platform' => 'myspace', 'url' => 'not-a-url']],
        ]));

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });
});
