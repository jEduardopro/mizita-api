<?php

declare(strict_types=1);

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Infrastructure\Eloquent\Mappers\BusinessMapper;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use App\Models\User;
use App\Shared\ValueObjects\CurrencyCode;
use Tests\Support\Businesses\OnboardingFixtures;

const BUSINESS_MAPPER_INDUSTRY_KEY = 13;

const BUSINESS_MAPPER_CLOSER_KEY = 21;

const BUSINESS_MAPPER_CLOSED_AT = '2026-02-01T10:00:00+00:00';

const BUSINESS_MAPPER_PURGED_AT = '2026-03-04T03:00:00+00:00';

function closingAccountRow(): User
{
    $account = new User;

    $account->setRawAttributes([
        'id' => BUSINESS_MAPPER_CLOSER_KEY,
        'uuid' => OnboardingFixtures::OWNER_ACCOUNT_ID,
    ], true);

    return $account;
}

function closedBusinessRow(?DateTimeImmutable $purgedAt = null): BusinessModel
{
    $closedAt = new DateTimeImmutable(BUSINESS_MAPPER_CLOSED_AT);

    $model = businessRow([
        'closed_at' => $closedAt,
        'closed_by_account_id' => BUSINESS_MAPPER_CLOSER_KEY,
        'purged_at' => $purgedAt,
        'deleted_at' => $closedAt,
    ]);

    $model->setRelation('closedBy', closingAccountRow());

    return $model;
}

function closedMapperBusiness(): Business
{
    $business = OnboardingFixtures::business();

    $business->close(OnboardingFixtures::OWNER_ACCOUNT_ID, new DateTimeImmutable(BUSINESS_MAPPER_CLOSED_AT));

    return $business;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function businessRow(array $overrides = []): BusinessModel
{
    $model = new BusinessModel;

    $model->setRawAttributes([
        'id' => 9,
        'uuid' => OnboardingFixtures::GENERATED_BUSINESS_ID,
        'name' => OnboardingFixtures::NAME,
        'slug' => OnboardingFixtures::SLUG,
        'industry_id' => BUSINESS_MAPPER_INDUSTRY_KEY,
        'timezone' => OnboardingFixtures::TIMEZONE,
        'contact_email' => 'hola@barberia.com',
        'about' => 'Barbería clásica desde 2019.',
        'currency_code' => 'MXN',
        'created_at' => OnboardingFixtures::now(),
        ...$overrides,
    ], true);

    $industry = new IndustryModel;
    $industry->setRawAttributes([
        'id' => BUSINESS_MAPPER_INDUSTRY_KEY,
        'uuid' => OnboardingFixtures::INDUSTRY_ID,
    ], true);

    $model->setRelation('industry', $industry);
    $model->setRelation('closedBy', null);

    return $model;
}

describe('reading a row', function () {
    it('restores every value the row carries', function () {
        $business = (new BusinessMapper)->toEntity(businessRow());

        expect($business)->toBeInstanceOf(Business::class)
            ->and($business->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($business->name())->toBe(OnboardingFixtures::NAME)
            ->and($business->slug())->toBe(OnboardingFixtures::SLUG)
            ->and($business->timezone())->toBe(OnboardingFixtures::TIMEZONE)
            ->and($business->contactEmail())->toBe('hola@barberia.com')
            ->and($business->about())->toBe('Barbería clásica desde 2019.')
            ->and($business->currency())->toBe('MXN')
            ->and($business->createdAt)->toEqual(OnboardingFixtures::now());
    });

    it('takes the industry as the uuid on the relation, never the key the row holds', function () {
        $business = (new BusinessMapper)->toEntity(businessRow());

        expect($business->industryId())->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($business->industryId())->not->toBe((string) BUSINESS_MAPPER_INDUSTRY_KEY);
    });

    it('carries the uuid as its identity, never the primary key', function () {
        $business = (new BusinessMapper)->toEntity(businessRow());

        expect($business->id)->toBeString()
            ->and($business->id)->not->toBe('9');
    });

    it('restores a business that filled neither optional field', function () {
        $business = (new BusinessMapper)->toEntity(businessRow(['contact_email' => null, 'about' => null]));

        expect($business->contactEmail())->toBeNull()
            ->and($business->about())->toBeNull();
    });

    it('restores each optional field on its own', function (string $column, string $reader) {
        $business = (new BusinessMapper)->toEntity(businessRow([$column => null]));

        expect($business->{$reader}())->toBeNull();
    })->with([
        'no contact email' => ['contact_email', 'contactEmail'],
        'no description' => ['about', 'about'],
    ]);

    it('restores a row without holding it to the invariants creation enforces', function () {
        $business = (new BusinessMapper)->toEntity(businessRow([
            'name' => '',
            'contact_email' => 'hola@BARBERIA',
            'about' => '   ',
            'currency_code' => 'mxn',
        ]));

        expect($business->name())->toBe('')
            ->and($business->contactEmail())->toBe('hola@BARBERIA')
            ->and($business->about())->toBe('   ')
            ->and($business->currency())->toBe('mxn');
    });

    it('keeps unicode in the description exactly as the row stores it', function () {
        $about = "Barbería Ñandú 💈\nDesde 2019.";

        expect((new BusinessMapper)->toEntity(businessRow(['about' => $about]))->about())->toBe($about);
    });
});

describe('writing a row', function () {
    it('writes exactly the columns the row owns', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(
                contactEmail: ContactEmail::fromString('hola@barberia.com'),
                about: About::fromString('Barbería clásica desde 2019.'),
                currency: CurrencyCode::fromString('MXN'),
            ),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            null,
        );

        expect($attributes)->toBe([
            'uuid' => OnboardingFixtures::GENERATED_BUSINESS_ID,
            'name' => OnboardingFixtures::NAME,
            'slug' => OnboardingFixtures::SLUG,
            'industry_id' => BUSINESS_MAPPER_INDUSTRY_KEY,
            'timezone' => OnboardingFixtures::TIMEZONE,
            'contact_email' => 'hola@barberia.com',
            'about' => 'Barbería clásica desde 2019.',
            'currency_code' => 'MXN',
            'closed_at' => null,
            'closed_by_account_id' => null,
            'purged_at' => null,
            'deleted_at' => null,
        ]);
    });

    it('writes the industry as the key it was handed, never as the uuid the entity carries', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            null,
        );

        expect($attributes['industry_id'])->toBe(BUSINESS_MAPPER_INDUSTRY_KEY)
            ->toBeInt()
            ->and($attributes['industry_id'])->not->toBe(OnboardingFixtures::INDUSTRY_ID);
    });

    it('writes null for the optional fields a business never filled', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            null,
        );

        expect($attributes['contact_email'])->toBeNull()
            ->and($attributes['about'])->toBeNull();
    });

    it('writes the default currency for a business that never chose one', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            null,
        );

        expect($attributes['currency_code'])->toBe('MXN')->toBeString();
    });

    it('writes the currency folded, as the value object normalised it', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(currency: CurrencyCode::fromString('usd')),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            null,
        );

        expect($attributes['currency_code'])->toBe('USD');
    });

    it('leaves the identity and the bookkeeping timestamps to the database', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            null,
        );

        expect($attributes)->not->toHaveKey('id')
            ->and($attributes)->not->toHaveKey('created_at')
            ->and($attributes)->not->toHaveKey('updated_at');
    });

    it('owns the soft delete column, because closing the business is what trashes the row', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            null,
        );

        expect($attributes)->toHaveKey('deleted_at')
            ->and($attributes['deleted_at'])->toBeNull();
    });

    it('names the columns as the table spells them, not as the entity does', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            OnboardingFixtures::business(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            null,
        );

        expect($attributes)->toHaveKeys(['contact_email', 'about', 'currency_code'])
            ->and($attributes)->not->toHaveKey('currency')
            ->and($attributes)->not->toHaveKey('contactEmail');
    });
});

it('carries a business through both directions unchanged', function () {
    $mapper = new BusinessMapper;

    $attributes = $mapper->toAttributes(
        OnboardingFixtures::business(
            contactEmail: ContactEmail::fromString('  Hola@BARBERIA.com '),
            about: About::fromString('  Barbería clásica desde 2019.  '),
            currency: CurrencyCode::fromString('usd'),
        ),
        BUSINESS_MAPPER_INDUSTRY_KEY,
        null,
    );

    $restored = $mapper->toEntity(businessRow([...$attributes, 'created_at' => OnboardingFixtures::now()]));

    expect($restored->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
        ->and($restored->name())->toBe(OnboardingFixtures::NAME)
        ->and($restored->slug())->toBe(OnboardingFixtures::SLUG)
        ->and($restored->industryId())->toBe(OnboardingFixtures::INDUSTRY_ID)
        ->and($restored->timezone())->toBe(OnboardingFixtures::TIMEZONE)
        ->and($restored->contactEmail())->toBe('Hola@barberia.com')
        ->and($restored->about())->toBe('Barbería clásica desde 2019.')
        ->and($restored->currency())->toBe('USD');
});

it('carries a business with nothing optional filled through both directions unchanged', function () {
    $mapper = new BusinessMapper;

    $attributes = $mapper->toAttributes(OnboardingFixtures::business(), BUSINESS_MAPPER_INDUSTRY_KEY, null);
    $restored = $mapper->toEntity(businessRow([...$attributes, 'created_at' => OnboardingFixtures::now()]));

    expect($restored->contactEmail())->toBeNull()
        ->and($restored->about())->toBeNull()
        ->and($restored->currency())->toBe('MXN');
});

describe('reading the closure off a row', function () {
    it('restores an open business with no closure at all', function () {
        $business = (new BusinessMapper)->toEntity(businessRow());

        expect($business->isClosed())->toBeFalse()
            ->and($business->closedAt())->toBeNull()
            ->and($business->closedByAccountId())->toBeNull()
            ->and($business->isPurged())->toBeFalse()
            ->and($business->purgedAt())->toBeNull();
    });

    it('restores when the business was closed', function () {
        $business = (new BusinessMapper)->toEntity(closedBusinessRow());

        expect($business->isClosed())->toBeTrue()
            ->and($business->closedAt())->toEqual(new DateTimeImmutable(BUSINESS_MAPPER_CLOSED_AT))
            ->and($business->closedAt())->toBeInstanceOf(DateTimeImmutable::class)
            ->and($business->isPurged())->toBeFalse();
    });

    it('takes the closing account as the uuid on the relation, never the key the row holds', function () {
        $business = (new BusinessMapper)->toEntity(closedBusinessRow());

        expect($business->closedByAccountId())->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->and($business->closedByAccountId())->not->toBe((string) BUSINESS_MAPPER_CLOSER_KEY);
    });

    it('restores when the business was purged', function () {
        $business = (new BusinessMapper)->toEntity(closedBusinessRow(new DateTimeImmutable(BUSINESS_MAPPER_PURGED_AT)));

        expect($business->isPurged())->toBeTrue()
            ->and($business->purgedAt())->toEqual(new DateTimeImmutable(BUSINESS_MAPPER_PURGED_AT));
    });
});

describe('writing the closure to a row', function () {
    it('writes the closing instant, the closing account key and trashes the row at the same instant', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            closedMapperBusiness(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            BUSINESS_MAPPER_CLOSER_KEY,
        );

        expect($attributes['closed_at'])->toEqual(new DateTimeImmutable(BUSINESS_MAPPER_CLOSED_AT))
            ->and($attributes['deleted_at'])->toEqual($attributes['closed_at'])
            ->and($attributes['closed_by_account_id'])->toBe(BUSINESS_MAPPER_CLOSER_KEY)
            ->and($attributes['purged_at'])->toBeNull();
    });

    it('writes the closing account as the key it was handed, never as the uuid the entity carries', function () {
        $attributes = (new BusinessMapper)->toAttributes(
            closedMapperBusiness(),
            BUSINESS_MAPPER_INDUSTRY_KEY,
            BUSINESS_MAPPER_CLOSER_KEY,
        );

        expect($attributes['closed_by_account_id'])->toBeInt()
            ->and($attributes['closed_by_account_id'])->not->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID);
    });

    it('writes the purge instant once the business has been purged', function () {
        $business = closedMapperBusiness();
        $business->markPurged(new DateTimeImmutable(BUSINESS_MAPPER_PURGED_AT));

        $attributes = (new BusinessMapper)->toAttributes($business, BUSINESS_MAPPER_INDUSTRY_KEY, BUSINESS_MAPPER_CLOSER_KEY);

        expect($attributes['purged_at'])->toEqual(new DateTimeImmutable(BUSINESS_MAPPER_PURGED_AT))
            ->and($attributes['deleted_at'])->toEqual(new DateTimeImmutable(BUSINESS_MAPPER_CLOSED_AT));
    });

    it('stores every closure instant in UTC whatever offset the entity holds it in', function () {
        $business = OnboardingFixtures::business();
        $business->close(OnboardingFixtures::OWNER_ACCOUNT_ID, new DateTimeImmutable('2026-03-29T03:30:00+02:00'));
        $business->markPurged(new DateTimeImmutable('2026-10-25T02:30:00+01:00'));

        $attributes = (new BusinessMapper)->toAttributes($business, BUSINESS_MAPPER_INDUSTRY_KEY, BUSINESS_MAPPER_CLOSER_KEY);

        expect($attributes['closed_at']->format(DATE_ATOM))->toBe('2026-03-29T01:30:00+00:00')
            ->and($attributes['deleted_at']->format(DATE_ATOM))->toBe('2026-03-29T01:30:00+00:00')
            ->and($attributes['purged_at']->format(DATE_ATOM))->toBe('2026-10-25T01:30:00+00:00');
    });

    it('clears every closure column when the business is reopened, which restores the row', function () {
        $business = closedMapperBusiness();
        $business->reopen(OnboardingFixtures::OWNER_ACCOUNT_ID);

        $attributes = (new BusinessMapper)->toAttributes($business, BUSINESS_MAPPER_INDUSTRY_KEY, null);

        expect($attributes['closed_at'])->toBeNull()
            ->and($attributes['closed_by_account_id'])->toBeNull()
            ->and($attributes['purged_at'])->toBeNull()
            ->and($attributes['deleted_at'])->toBeNull();
    });
});

it('carries a closed and purged business through both directions unchanged', function () {
    $mapper = new BusinessMapper;

    $business = closedMapperBusiness();
    $business->markPurged(new DateTimeImmutable(BUSINESS_MAPPER_PURGED_AT));

    $row = businessRow([
        ...$mapper->toAttributes($business, BUSINESS_MAPPER_INDUSTRY_KEY, BUSINESS_MAPPER_CLOSER_KEY),
        'created_at' => OnboardingFixtures::now(),
    ]);
    $row->setRelation('closedBy', closingAccountRow());

    $restored = $mapper->toEntity($row);

    expect($restored->closedAt())->toEqual(new DateTimeImmutable(BUSINESS_MAPPER_CLOSED_AT))
        ->and($restored->closedByAccountId())->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
        ->and($restored->purgedAt())->toEqual(new DateTimeImmutable(BUSINESS_MAPPER_PURGED_AT))
        ->and($restored->purgeScheduledAt())->toEqual($business->purgeScheduledAt());
});
