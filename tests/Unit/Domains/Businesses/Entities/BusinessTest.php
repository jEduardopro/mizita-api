<?php

declare(strict_types=1);

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessAlreadyClosed;
use App\Domains\Businesses\Exceptions\BusinessNotClosed;
use App\Domains\Businesses\Exceptions\BusinessNotDueForPurge;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use App\Shared\ValueObjects\CurrencyCode;
use Tests\Support\Businesses\ClosureFixtures;
use Tests\Support\Businesses\OnboardingFixtures;

const BUSINESS_OTHER_INDUSTRY_ID = '01930000-0000-7000-8000-0000000000f2';

beforeEach(function () {
    $this->createBusiness = fn (
        string $name = OnboardingFixtures::NAME,
        ?ContactEmail $contactEmail = null,
        ?About $about = null,
        ?CurrencyCode $currency = null,
    ): Business => Business::create(
        id: OnboardingFixtures::GENERATED_BUSINESS_ID,
        name: $name,
        slug: Slug::fromName(OnboardingFixtures::NAME),
        industryId: OnboardingFixtures::INDUSTRY_ID,
        timezone: Timezone::fromString(OnboardingFixtures::TIMEZONE),
        now: OnboardingFixtures::now(),
        contactEmail: $contactEmail,
        about: $about,
        currency: $currency,
    );

    $this->closedBusiness = fn (): Business => ClosureFixtures::closedBusiness();
    $this->purgedBusiness = fn (): Business => ClosureFixtures::purgedBusiness();
});

describe('creating a business', function () {
    it('holds everything it was created with', function () {
        $business = ($this->createBusiness)();

        expect($business->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($business->name())->toBe('Barbería Ñandú')
            ->and($business->slug())->toBe('barberia-nandu')
            ->and($business->industryId())->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($business->timezone())->toBe('Europe/Madrid')
            ->and($business->createdAt)->toEqual(OnboardingFixtures::now());
    });

    it('hands its value objects out as the primitives a DTO is made of', function () {
        $business = ($this->createBusiness)();

        expect($business->slug())->toBeString()
            ->and($business->timezone())->toBeString();
    });

    it('trims the name', function () {
        expect(($this->createBusiness)('   Barbería Ñandú   ')->name())->toBe('Barbería Ñandú');
    });

    it('keeps accents and punctuation in the name, which is not a slug', function (string $name) {
        expect(($this->createBusiness)($name)->name())->toBe($name);
    })->with([
        'accents' => 'Barbería Ñandú',
        'punctuation' => 'Café & Té, S.L.',
        'another script' => 'Салон Красоты',
        'emoji' => 'Barbería 💈',
    ]);

    it('refuses a blank name', function (string $name) {
        expect(fn () => ($this->createBusiness)($name))
            ->toThrow(InvalidBusinessName::class, 'A business name cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'mixed whitespace' => " \t\n ",
    ]);

    it('accepts a single character, since only emptiness is refused', function () {
        expect(($this->createBusiness)('X')->name())->toBe('X');
    });

    it('leaves the brand fields at their defaults when onboarding omits them', function () {
        $business = ($this->createBusiness)();

        expect($business->contactEmail())->toBeNull()
            ->and($business->about())->toBeNull()
            ->and($business->currency())->toBe('MXN');
    });

    it('holds the brand fields when it is created with them', function () {
        $business = ($this->createBusiness)(
            contactEmail: ContactEmail::fromString('hola@barberia.com'),
            about: About::fromString('Barbería clásica desde 2019.'),
            currency: CurrencyCode::fromString('USD'),
        );

        expect($business->contactEmail())->toBe('hola@barberia.com')
            ->and($business->about())->toBe('Barbería clásica desde 2019.')
            ->and($business->currency())->toBe('USD');
    });

    it('hands the brand fields out as the primitives a DTO is made of', function () {
        $business = ($this->createBusiness)(
            contactEmail: ContactEmail::fromString('hola@barberia.com'),
            about: About::fromString('Cortes y color.'),
            currency: CurrencyCode::fromString('MXN'),
        );

        expect($business->contactEmail())->toBeString()
            ->and($business->about())->toBeString()
            ->and($business->currency())->toBeString();
    });
});

describe('rehydrating from storage', function () {
    it('skips the creation-time rules by design', function () {
        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: '',
            slug: Slug::restore('Barberia_Nandu'),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore('europe/madrid'),
            createdAt: OnboardingFixtures::now(),
        );

        expect($business->name())->toBe('')
            ->and($business->slug())->toBe('Barberia_Nandu')
            ->and($business->timezone())->toBe('europe/madrid');
    });

    it('does not trim, because storage is reproduced rather than corrected', function () {
        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: '  Barbería Ñandú  ',
            slug: Slug::restore(OnboardingFixtures::SLUG),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore(OnboardingFixtures::TIMEZONE),
            createdAt: OnboardingFixtures::now(),
        );

        expect($business->name())->toBe('  Barbería Ñandú  ');
    });

    it('defaults the brand fields for a row written before the columns existed', function () {
        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: OnboardingFixtures::NAME,
            slug: Slug::restore(OnboardingFixtures::SLUG),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore(OnboardingFixtures::TIMEZONE),
            createdAt: OnboardingFixtures::now(),
        );

        expect($business->contactEmail())->toBeNull()
            ->and($business->about())->toBeNull()
            ->and($business->currency())->toBe('MXN');
    });

    it('reproduces brand fields storage would not accept today', function () {
        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: OnboardingFixtures::NAME,
            slug: Slug::restore(OnboardingFixtures::SLUG),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore(OnboardingFixtures::TIMEZONE),
            createdAt: OnboardingFixtures::now(),
            contactEmail: ContactEmail::restore('hola@BARBERIA'),
            about: About::restore('   '),
            currency: CurrencyCode::restore('mxn'),
        );

        expect($business->contactEmail())->toBe('hola@BARBERIA')
            ->and($business->about())->toBe('   ')
            ->and($business->currency())->toBe('mxn');
    });

    it('keeps the stored creation instant instead of taking a new one', function () {
        $writtenAt = new DateTimeImmutable('2019-11-02T07:45:13+00:00');

        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: OnboardingFixtures::NAME,
            slug: Slug::restore(OnboardingFixtures::SLUG),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore(OnboardingFixtures::TIMEZONE),
            createdAt: $writtenAt,
        );

        expect($business->createdAt)->toEqual($writtenAt)
            ->and($business->createdAt)->not->toEqual(OnboardingFixtures::now());
    });
});

describe('renaming', function () {
    it('takes the new name', function () {
        $business = ($this->createBusiness)();

        $business->rename('Barbería del Centro');

        expect($business->name())->toBe('Barbería del Centro');
    });

    it('never touches the slug, because the booking page URL is already shared', function () {
        $business = ($this->createBusiness)();
        $slugBeforeRenaming = $business->slug();

        $business->rename('Something Entirely Different');

        expect($business->slug())->toBe($slugBeforeRenaming)
            ->and($business->slug())->toBe('barberia-nandu');
    });

    it('keeps the slug even when the new name would produce another one', function (string $name) {
        $business = ($this->createBusiness)();

        $business->rename($name);

        expect($business->slug())->toBe('barberia-nandu');
    })->with([
        'a different word' => 'Peluquería Ámbar',
        'the same name uppercased' => 'BARBERÍA ÑANDÚ',
        'a name that slugs to nothing new' => 'Barberia Nandu',
    ]);

    it('trims the new name, exactly as creation does', function () {
        $business = ($this->createBusiness)();

        $business->rename('   Barbería del Centro   ');

        expect($business->name())->toBe('Barbería del Centro');
    });

    it('keeps accents and punctuation in the new name', function (string $name) {
        $business = ($this->createBusiness)();

        $business->rename($name);

        expect($business->name())->toBe($name);
    })->with([
        'accents' => 'Barbería Ñandú',
        'punctuation' => 'Café & Té, S.L.',
        'another script' => 'Салон Красоты',
        'emoji' => 'Barbería 💈',
    ]);

    it('refuses a blank name, holding a rename to the same rule as creation', function (string $name) {
        $business = ($this->createBusiness)();

        expect(fn () => $business->rename($name))
            ->toThrow(InvalidBusinessName::class, 'A business name cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'mixed whitespace' => " \t\n ",
    ]);

    it('leaves the old name in place when the new one is refused', function () {
        $business = ($this->createBusiness)();

        try {
            $business->rename('   ');
        } catch (InvalidBusinessName) {
        }

        expect($business->name())->toBe(OnboardingFixtures::NAME);
    });

    it('leaves everything else untouched', function () {
        $business = ($this->createBusiness)(
            contactEmail: ContactEmail::fromString('hola@barberia.com'),
            about: About::fromString('Cortes y color.'),
            currency: CurrencyCode::fromString('USD'),
        );

        $business->rename('Barbería del Centro');

        expect($business->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($business->industryId())->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($business->timezone())->toBe(OnboardingFixtures::TIMEZONE)
            ->and($business->contactEmail())->toBe('hola@barberia.com')
            ->and($business->about())->toBe('Cortes y color.')
            ->and($business->currency())->toBe('USD')
            ->and($business->createdAt)->toEqual(OnboardingFixtures::now());
    });
});

describe('reclassifying the business', function () {
    it('takes the new industry', function () {
        $business = ($this->createBusiness)();

        $business->reclassify(BUSINESS_OTHER_INDUSTRY_ID);

        expect($business->industryId())->toBe(BUSINESS_OTHER_INDUSTRY_ID);
    });

    it('accepts the industry it already had', function () {
        $business = ($this->createBusiness)();

        $business->reclassify(OnboardingFixtures::INDUSTRY_ID);

        expect($business->industryId())->toBe(OnboardingFixtures::INDUSTRY_ID);
    });

    it('holds the industry as a uuid, never as the key a row would carry', function () {
        $business = ($this->createBusiness)();

        $business->reclassify(BUSINESS_OTHER_INDUSTRY_ID);

        expect($business->industryId())->toBeString()
            ->and($business->industryId())->toMatch('/^[0-9a-f-]{36}$/i')
            ->and($business->industryId())->not->toBe('7');
    });

    it('never touches the slug, because the booking page URL is already shared', function () {
        $business = ($this->createBusiness)();

        $business->reclassify(BUSINESS_OTHER_INDUSTRY_ID);

        expect($business->slug())->toBe(OnboardingFixtures::SLUG);
    });

    it('leaves everything else untouched', function () {
        $business = ($this->createBusiness)(
            contactEmail: ContactEmail::fromString('hola@barberia.com'),
            about: About::fromString('Cortes y color.'),
            currency: CurrencyCode::fromString('USD'),
        );

        $business->reclassify(BUSINESS_OTHER_INDUSTRY_ID);

        expect($business->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($business->name())->toBe(OnboardingFixtures::NAME)
            ->and($business->slug())->toBe(OnboardingFixtures::SLUG)
            ->and($business->timezone())->toBe(OnboardingFixtures::TIMEZONE)
            ->and($business->contactEmail())->toBe('hola@barberia.com')
            ->and($business->about())->toBe('Cortes y color.')
            ->and($business->currency())->toBe('USD')
            ->and($business->createdAt)->toEqual(OnboardingFixtures::now());
    });

    it('takes whatever id it is handed, because catalog membership is not the entity to rule on', function (string $industryId) {
        $business = ($this->createBusiness)();

        $business->reclassify($industryId);

        expect($business->industryId())->toBe($industryId);
    })->with([
        'another catalog entry' => BUSINESS_OTHER_INDUSTRY_ID,
        'an id nobody has' => '01930000-0000-7000-8000-0000000000ff',
    ]);
});

describe('changing the timezone', function () {
    it('takes the new zone', function () {
        $business = ($this->createBusiness)();

        $business->changeTimezone(Timezone::fromString('America/Mexico_City'));

        expect($business->timezone())->toBe('America/Mexico_City');
    });

    it('accepts the zone it already had', function () {
        $business = ($this->createBusiness)();

        $business->changeTimezone(Timezone::fromString(OnboardingFixtures::TIMEZONE));

        expect($business->timezone())->toBe(OnboardingFixtures::TIMEZONE);
    });

    it('leaves the name and the slug alone', function () {
        $business = ($this->createBusiness)();

        $business->changeTimezone(Timezone::fromString('UTC'));

        expect($business->name())->toBe(OnboardingFixtures::NAME)
            ->and($business->slug())->toBe(OnboardingFixtures::SLUG);
    });
});

describe('changing the contact email', function () {
    it('takes the new address', function () {
        $business = ($this->createBusiness)();

        $business->changeContactEmail(ContactEmail::fromString('citas@barberia.com'));

        expect($business->contactEmail())->toBe('citas@barberia.com');
    });

    it('replaces an address it already had', function () {
        $business = ($this->createBusiness)(contactEmail: ContactEmail::fromString('hola@barberia.com'));

        $business->changeContactEmail(ContactEmail::fromString('citas@barberia.com'));

        expect($business->contactEmail())->toBe('citas@barberia.com');
    });

    it('clears the address when handed nothing', function () {
        $business = ($this->createBusiness)(contactEmail: ContactEmail::fromString('hola@barberia.com'));

        $business->changeContactEmail(null);

        expect($business->contactEmail())->toBeNull();
    });

    it('stays empty when nothing is cleared twice', function () {
        $business = ($this->createBusiness)();

        $business->changeContactEmail(null);

        expect($business->contactEmail())->toBeNull();
    });

    it('stores the value the value object normalised, not the one the caller typed', function () {
        $business = ($this->createBusiness)();

        $business->changeContactEmail(ContactEmail::fromString('  Hola@BARBERIA.COM  '));

        expect($business->contactEmail())->toBe('Hola@barberia.com');
    });
});

describe('describing the business', function () {
    it('takes the new description', function () {
        $business = ($this->createBusiness)();

        $business->describeAs(About::fromString('Cortes y color desde 2019.'));

        expect($business->about())->toBe('Cortes y color desde 2019.');
    });

    it('replaces a description it already had', function () {
        $business = ($this->createBusiness)(about: About::fromString('Cortes.'));

        $business->describeAs(About::fromString('Cortes y color.'));

        expect($business->about())->toBe('Cortes y color.');
    });

    it('clears the description when handed nothing', function () {
        $business = ($this->createBusiness)(about: About::fromString('Cortes.'));

        $business->describeAs(null);

        expect($business->about())->toBeNull();
    });

    it('clears the description when handed what fromNullable makes of an empty box', function () {
        $business = ($this->createBusiness)(about: About::fromString('Cortes.'));

        $business->describeAs(About::fromNullable('   '));

        expect($business->about())->toBeNull();
    });
});

describe('changing the currency', function () {
    it('takes the new currency', function () {
        $business = ($this->createBusiness)();

        $business->changeCurrency(CurrencyCode::fromString('USD'));

        expect($business->currency())->toBe('USD');
    });

    it('stores the folded code, so the entity never sees a lower case currency', function () {
        $business = ($this->createBusiness)();

        $business->changeCurrency(CurrencyCode::fromString('usd'));

        expect($business->currency())->toBe('USD');
    });

    it('accepts the default back after another currency was set', function () {
        $business = ($this->createBusiness)(currency: CurrencyCode::fromString('USD'));

        $business->changeCurrency(CurrencyCode::default());

        expect($business->currency())->toBe('MXN');
    });

    it('leaves the other brand fields alone', function () {
        $business = ($this->createBusiness)(
            contactEmail: ContactEmail::fromString('hola@barberia.com'),
            about: About::fromString('Cortes.'),
        );

        $business->changeCurrency(CurrencyCode::fromString('EUR'));

        expect($business->contactEmail())->toBe('hola@barberia.com')
            ->and($business->about())->toBe('Cortes.');
    });
});

describe('a business nobody has closed', function () {
    it('starts life open, unpurged and with no purge on the calendar', function () {
        $business = ($this->createBusiness)();

        expect($business->isClosed())->toBeFalse()
            ->and($business->closedAt())->toBeNull()
            ->and($business->closedByAccountId())->toBeNull()
            ->and($business->isPurged())->toBeFalse()
            ->and($business->purgedAt())->toBeNull()
            ->and($business->purgeScheduledAt())->toBeNull();
    });
});

describe('closing the business', function () {
    it('records the instant and the account that closed it', function () {
        $business = ($this->closedBusiness)();

        expect($business->isClosed())->toBeTrue()
            ->and($business->closedAt())->toEqual(ClosureFixtures::closedAt())
            ->and($business->closedByAccountId())->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->and($business->isPurged())->toBeFalse();
    });

    it('schedules the purge thirty days after the closing instant', function () {
        expect(($this->closedBusiness)()->purgeScheduledAt())
            ->toEqual(ClosureFixtures::purgeDueAt());
    });

    it('counts thirty whole days of elapsed time across the spring-forward weekend when the clock speaks UTC', function () {
        $business = OnboardingFixtures::business();
        $closedAt = new DateTimeImmutable('2026-03-15T12:00:00+00:00');

        $business->close(OnboardingFixtures::OWNER_ACCOUNT_ID, $closedAt);

        expect($business->purgeScheduledAt()->getTimestamp() - $closedAt->getTimestamp())
            ->toBe(30 * 24 * 60 * 60);
    });

    it('refuses to close a business that is already closed', function () {
        $business = ($this->closedBusiness)();

        expect(fn () => $business->close(ClosureFixtures::STRANGER_ACCOUNT_ID, new DateTimeImmutable('2026-02-02T10:00:00+00:00')))
            ->toThrow(BusinessAlreadyClosed::class, 'Business ['.OnboardingFixtures::GENERATED_BUSINESS_ID.'] is already closed.');
    });

    it('keeps the first closure when a second one is refused', function () {
        $business = ($this->closedBusiness)();

        try {
            $business->close(ClosureFixtures::STRANGER_ACCOUNT_ID, new DateTimeImmutable('2026-02-02T10:00:00+00:00'));
        } catch (BusinessAlreadyClosed) {
        }

        expect($business->closedAt())->toEqual(ClosureFixtures::closedAt())
            ->and($business->closedByAccountId())->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->and($business->purgeScheduledAt())->toEqual(ClosureFixtures::purgeDueAt());
    });

    it('refuses to close a purged business too, because it is still closed', function () {
        expect(fn () => ($this->purgedBusiness)()->close(OnboardingFixtures::OWNER_ACCOUNT_ID, new DateTimeImmutable('2026-04-01T10:00:00+00:00')))
            ->toThrow(BusinessAlreadyClosed::class);
    });

    it('leaves the name and the slug in place, because both stay reserved', function () {
        $business = ($this->closedBusiness)();

        expect($business->name())->toBe(OnboardingFixtures::NAME)
            ->and($business->slug())->toBe(OnboardingFixtures::SLUG);
    });
});

describe('reopening the business', function () {
    it('refuses a business that was never closed', function () {
        expect(fn () => ($this->createBusiness)()->reopen(OnboardingFixtures::OWNER_ACCOUNT_ID))
            ->toThrow(BusinessNotClosed::class, 'Business ['.OnboardingFixtures::GENERATED_BUSINESS_ID.'] is not closed.');
    });

    it('refuses an account other than the one that closed it', function () {
        expect(fn () => ($this->closedBusiness)()->reopen(ClosureFixtures::STRANGER_ACCOUNT_ID))
            ->toThrow(
                BusinessNotClosed::class,
                'Business ['.OnboardingFixtures::GENERATED_BUSINESS_ID.'] was not closed by account ['.ClosureFixtures::STRANGER_ACCOUNT_ID.'].',
            );
    });

    it('stays closed when another account is refused', function () {
        $business = ($this->closedBusiness)();

        try {
            $business->reopen(ClosureFixtures::STRANGER_ACCOUNT_ID);
        } catch (BusinessNotClosed) {
        }

        expect($business->isClosed())->toBeTrue()
            ->and($business->closedByAccountId())->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->and($business->closedAt())->toEqual(ClosureFixtures::closedAt());
    });

    it('comes back with its data intact during the grace period', function () {
        $reopening = ($this->closedBusiness)()->reopen(OnboardingFixtures::OWNER_ACCOUNT_ID);

        expect($reopening->requiresReprovisioning())->toBeFalse();
    });

    it('asks for reprovisioning once the business has been purged', function () {
        $reopening = ($this->purgedBusiness)()->reopen(OnboardingFixtures::OWNER_ACCOUNT_ID);

        expect($reopening->requiresReprovisioning())->toBeTrue();
    });

    it('clears the closure so the business is open again', function (string $state) {
        $business = $state === 'purged' ? ($this->purgedBusiness)() : ($this->closedBusiness)();

        $business->reopen(OnboardingFixtures::OWNER_ACCOUNT_ID);

        expect($business->isClosed())->toBeFalse()
            ->and($business->closedAt())->toBeNull()
            ->and($business->closedByAccountId())->toBeNull()
            ->and($business->isPurged())->toBeFalse()
            ->and($business->purgedAt())->toBeNull()
            ->and($business->purgeScheduledAt())->toBeNull();
    })->with(['in grace' => 'closed', 'after the purge' => 'purged']);

    it('refuses a second reopening, because the first one already opened it', function () {
        $business = ($this->closedBusiness)();
        $business->reopen(OnboardingFixtures::OWNER_ACCOUNT_ID);

        expect(fn () => $business->reopen(OnboardingFixtures::OWNER_ACCOUNT_ID))
            ->toThrow(BusinessNotClosed::class);
    });

    it('can be closed again once reopened, starting a fresh retention', function () {
        $business = ($this->closedBusiness)();
        $business->reopen(OnboardingFixtures::OWNER_ACCOUNT_ID);

        $business->close(OnboardingFixtures::OWNER_ACCOUNT_ID, new DateTimeImmutable('2026-05-01T10:00:00+00:00'));

        expect($business->purgeScheduledAt())->toEqual(new DateTimeImmutable('2026-05-31T10:00:00+00:00'));
    });
});

describe('marking the business purged', function () {
    it('refuses a business that is not closed', function () {
        expect(fn () => ($this->createBusiness)()->markPurged(ClosureFixtures::purgeDueAt()))
            ->toThrow(BusinessNotClosed::class);
    });

    it('refuses one second before the retention ends', function () {
        $business = ($this->closedBusiness)();

        expect(fn () => $business->markPurged(new DateTimeImmutable(ClosureFixtures::ONE_SECOND_BEFORE_PURGE_DUE)))
            ->toThrow(
                BusinessNotDueForPurge::class,
                'Business ['.OnboardingFixtures::GENERATED_BUSINESS_ID.'] cannot be purged before [2026-03-03T10:00:00+00:00].',
            );
    });

    it('stays unpurged when it is refused as not due', function () {
        $business = ($this->closedBusiness)();

        try {
            $business->markPurged(new DateTimeImmutable(ClosureFixtures::ONE_SECOND_BEFORE_PURGE_DUE));
        } catch (BusinessNotDueForPurge) {
        }

        expect($business->isPurged())->toBeFalse()
            ->and($business->purgedAt())->toBeNull();
    });

    it('purges at the exact instant the retention ends', function () {
        $business = ($this->closedBusiness)();

        $business->markPurged(ClosureFixtures::purgeDueAt());

        expect($business->isPurged())->toBeTrue()
            ->and($business->purgedAt())->toEqual(ClosureFixtures::purgeDueAt());
    });

    it('purges any time after the retention ends, stamping the instant it was handed', function () {
        $business = ($this->closedBusiness)();

        $business->markPurged(new DateTimeImmutable('2026-06-01T03:00:00+00:00'));

        expect($business->purgedAt())->toEqual(new DateTimeImmutable('2026-06-01T03:00:00+00:00'));
    });

    it('stays closed after the purge, with the original closure untouched', function () {
        $business = ($this->purgedBusiness)();

        expect($business->isClosed())->toBeTrue()
            ->and($business->closedAt())->toEqual(ClosureFixtures::closedAt())
            ->and($business->closedByAccountId())->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID);
    });

    it('keeps the original purge instant when it is marked purged again', function () {
        $business = ($this->purgedBusiness)();

        $business->markPurged(new DateTimeImmutable('2026-07-01T03:00:00+00:00'));

        expect($business->purgedAt())->toEqual(ClosureFixtures::purgeDueAt());
    });
});

describe('rehydrating a closure from storage', function () {
    it('reproduces the closure and the purge the row carries', function () {
        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: OnboardingFixtures::NAME,
            slug: Slug::restore(OnboardingFixtures::SLUG),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore(OnboardingFixtures::TIMEZONE),
            createdAt: OnboardingFixtures::now(),
            closedAt: ClosureFixtures::closedAt(),
            closedByAccountId: OnboardingFixtures::OWNER_ACCOUNT_ID,
            purgedAt: ClosureFixtures::purgeDueAt(),
        );

        expect($business->isClosed())->toBeTrue()
            ->and($business->closedByAccountId())->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->and($business->isPurged())->toBeTrue()
            ->and($business->purgeScheduledAt())->toEqual(ClosureFixtures::purgeDueAt());
    });

    it('reproduces a purge without a closure, which creation could never produce', function () {
        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: OnboardingFixtures::NAME,
            slug: Slug::restore(OnboardingFixtures::SLUG),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore(OnboardingFixtures::TIMEZONE),
            createdAt: OnboardingFixtures::now(),
            purgedAt: ClosureFixtures::purgeDueAt(),
        );

        expect($business->isPurged())->toBeTrue()
            ->and($business->isClosed())->toBeFalse()
            ->and($business->purgeScheduledAt())->toBeNull();
    });

    it('refuses every reopening of a closure whose closing account is unknown', function () {
        $business = Business::restore(
            id: OnboardingFixtures::GENERATED_BUSINESS_ID,
            name: OnboardingFixtures::NAME,
            slug: Slug::restore(OnboardingFixtures::SLUG),
            industryId: OnboardingFixtures::INDUSTRY_ID,
            timezone: Timezone::restore(OnboardingFixtures::TIMEZONE),
            createdAt: OnboardingFixtures::now(),
            closedAt: ClosureFixtures::closedAt(),
        );

        expect(fn () => $business->reopen(OnboardingFixtures::OWNER_ACCOUNT_ID))
            ->toThrow(BusinessNotClosed::class, 'was not closed by account');
    });
});
