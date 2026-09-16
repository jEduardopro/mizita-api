<?php

declare(strict_types=1);

use App\Domains\Links\Infrastructure\Eloquent\Mappers\LinkMapper;
use App\Domains\Links\Infrastructure\Eloquent\Models\LinkModel;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Links\LinkFixtures;

/**
 * @param  array<string, mixed>  $overrides
 */
function linkRow(array $overrides = []): LinkModel
{
    $link = new LinkModel;

    $link->setRawAttributes([
        'id' => 7,
        'uuid' => LinkFixtures::LINK_ID,
        'linkable_type' => 'business',
        'linkable_id' => LinkFixtures::OWNER_KEY,
        'platform' => 'instagram',
        'url' => LinkFixtures::INSTAGRAM_URL,
        'position' => 10,
        'created_at' => LinkFixtures::now(),
        ...$overrides,
    ], true);

    return $link;
}

beforeEach(function () {
    $this->mapper = new LinkMapper;
});

describe('entity to row', function () {
    it('spreads the link across its six columns', function () {
        expect($this->mapper->toAttributes(LinkFixtures::link(position: 10), LinkFixtures::OWNER_KEY))->toBe([
            'uuid' => LinkFixtures::LINK_ID,
            'linkable_type' => 'business',
            'linkable_id' => LinkFixtures::OWNER_KEY,
            'platform' => 'instagram',
            'url' => LinkFixtures::INSTAGRAM_URL,
            'position' => 10,
        ]);
    });

    it('writes the owner as the int key it was handed, never as the uuid', function () {
        $attributes = $this->mapper->toAttributes(LinkFixtures::link(), LinkFixtures::OWNER_KEY);

        expect($attributes['linkable_id'])->toBe(LinkFixtures::OWNER_KEY)
            ->and($attributes['linkable_id'])->toBeInt()
            ->and($attributes)->not->toContain(FakeBusinessContext::BUSINESS_ID);
    });

    it('writes the owner kind as the stored alias', function (LinkOwnerType $ownerType, string $alias) {
        expect($this->mapper->toAttributes(LinkFixtures::link(ownerType: $ownerType), LinkFixtures::OWNER_KEY)['linkable_type'])->toBe($alias);
    })->with([
        'business' => [LinkOwnerType::Business, 'business'],
        'staff member' => [LinkOwnerType::StaffMember, 'staff_member'],
    ]);

    it('writes the platform as the key the column holds, never as the case name', function (LinkPlatform $platform) {
        expect($this->mapper->toAttributes(LinkFixtures::link(platform: $platform), LinkFixtures::OWNER_KEY)['platform'])->toBe($platform->value);
    })->with(fn () => LinkPlatform::cases());

    it('never writes the internal primary key', function () {
        expect($this->mapper->toAttributes(LinkFixtures::link(), LinkFixtures::OWNER_KEY))->not->toHaveKey('id');
    });
});

describe('row to entity', function () {
    it('takes the identity from the uuid column, not from the primary key', function () {
        expect($this->mapper->toEntity(linkRow(), FakeBusinessContext::BUSINESS_ID)->id)
            ->toBe(LinkFixtures::LINK_ID);
    });

    it('reads the owner back as the uuid it was handed, never as the column', function () {
        $link = $this->mapper->toEntity(linkRow(), FakeBusinessContext::BUSINESS_ID);

        expect($link->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($link->ownerId)->not->toBe((string) LinkFixtures::OWNER_KEY);
    });

    it('reads the owner kind back from the alias', function (string $alias, LinkOwnerType $ownerType) {
        expect($this->mapper->toEntity(linkRow(['linkable_type' => $alias]), FakeBusinessContext::BUSINESS_ID)
            ->ownerType)->toBe($ownerType);
    })->with([
        'business' => ['business', LinkOwnerType::Business],
        'staff member' => ['staff_member', LinkOwnerType::StaffMember],
    ]);

    it('reads the platform back as the enum the domain speaks', function (LinkPlatform $platform) {
        expect($this->mapper->toEntity(linkRow(['platform' => $platform->value]), FakeBusinessContext::BUSINESS_ID)
            ->platform)->toBe($platform);
    })->with(fn () => LinkPlatform::cases());

    it('reads the position back as an integer, not as the string a driver returns', function () {
        expect($this->mapper->toEntity(linkRow(['position' => '20']), FakeBusinessContext::BUSINESS_ID)->position())
            ->toBeInt()->toBe(20);
    });

    it('restores a stored url without asking the platform rules again', function () {
        expect($this->mapper->toEntity(linkRow(['url' => 'https://facebook.com/ada.salon']), FakeBusinessContext::BUSINESS_ID)
            ->url()->value)->toBe('https://facebook.com/ada.salon');
    });

    it('keeps the instant the row was created', function () {
        expect($this->mapper->toEntity(linkRow(), FakeBusinessContext::BUSINESS_ID)->createdAt)
            ->toEqual(LinkFixtures::now());
    });

    it('fails loudly on a row naming a platform the domain retired', function () {
        expect(fn () => $this->mapper->toEntity(linkRow(['platform' => 'myspace']), FakeBusinessContext::BUSINESS_ID))
            ->toThrow(ValueError::class);
    });
});

it('survives a full round trip without losing a fact', function () {
    $link = LinkFixtures::link(
        ownerType: LinkOwnerType::StaffMember,
        platform: LinkPlatform::YouTube,
        url: 'https://youtu.be/dQw4w9WgXcQ?t=42',
        position: 30,
    );

    $restored = $this->mapper->toEntity(
        linkRow([...$this->mapper->toAttributes($link, LinkFixtures::OWNER_KEY), 'created_at' => LinkFixtures::now()]),
        FakeBusinessContext::BUSINESS_ID,
    );

    expect($restored->id)->toBe($link->id)
        ->and($restored->ownerType)->toBe($link->ownerType)
        ->and($restored->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($restored->platform)->toBe($link->platform)
        ->and($restored->url()->value)->toBe($link->url()->value)
        ->and($restored->position())->toBe($link->position())
        ->and($restored->createdAt)->toEqual($link->createdAt);
});
