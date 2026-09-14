<?php

declare(strict_types=1);

use App\Domains\Phones\Application\Dtos\AttachPhoneInput;
use App\Domains\Phones\Application\Dtos\PhoneData;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\Contracts\PhoneNumberParser;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\PhoneNumbers;

/*
| Built from a mock and two fakes: no container, no migrations, no database.
|
| The behaviour worth protecting is that this is an upsert. An owner has at
| most one phone, enforced by a partial unique index, so a second call for the
| same owner has to move the record it finds instead of racing the constraint
| with a new row. Every test below is ultimately about that one save.
|
| Phones is a root domain - a phone belongs to an owner, and the owner's own
| domain is what belongs to a tenant - so there is deliberately no
| BusinessContext here. Nor is there a parser: a number arrives already
| established, because establishing it is the edge's job.
*/

const ATTACH_PHONE_GENERATED_ID = '01930000-0000-7000-8000-0000000000b1';

const ATTACH_PHONE_EXISTING_ID = '01930000-0000-7000-8000-0000000000b2';

function attachPhoneNow(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-01-01T12:00:00+00:00');
}

function attachPhoneInput(
    PhoneOwnerType $ownerType = PhoneOwnerType::Business,
    string $ownerId = 'business-1',
    string $nationalNumber = PhoneNumbers::MX_NATIONAL_NUMBER,
): AttachPhoneInput {
    return new AttachPhoneInput(
        ownerType: $ownerType,
        ownerId: $ownerId,
        number: PhoneNumbers::mexican($nationalNumber),
    );
}

beforeEach(function () {
    $this->phones = Mockery::mock(PhoneRepository::class);

    $this->useCase = new AttachPhone(
        $this->phones,
        new FixedIdGenerator(ATTACH_PHONE_GENERATED_ID),
        new FakeClock(attachPhoneNow()),
    );
});

describe('when the owner has no phone yet', function () {
    it('creates one and returns its data, field by field', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Business, 'business-1')
            ->andReturnNull();
        $this->phones->shouldReceive('save')->once()
            ->with(Mockery::on(fn (Phone $phone): bool => $phone->id === ATTACH_PHONE_GENERATED_ID
                && $phone->ownerType === PhoneOwnerType::Business
                && $phone->ownerId === 'business-1'
                && $phone->number()->e164() === '+525512345678'));

        $data = $this->useCase->handle(attachPhoneInput());

        expect($data)->toBeInstanceOf(PhoneData::class)
            ->and($data->id)->toBe(ATTACH_PHONE_GENERATED_ID)
            ->and($data->ownerType)->toBe(PhoneOwnerType::Business)
            ->and($data->ownerId)->toBe('business-1')
            ->and($data->number->e164())->toBe('+525512345678')
            ->and($data->createdAt)->toEqual(attachPhoneNow());
    });

    it('asks whether the owner already has one before writing anything', function () {
        // The lookup is what makes this an upsert rather than an insert, so it
        // has to happen first, not alongside.
        $callsInOrder = [];

        $this->phones->shouldReceive('findForOwner')->once()
            ->andReturnUsing(function () use (&$callsInOrder): ?Phone {
                $callsInOrder[] = 'findForOwner';

                return null;
            });
        $this->phones->shouldReceive('save')->once()
            ->andReturnUsing(function () use (&$callsInOrder): void {
                $callsInOrder[] = 'save';
            });

        $this->useCase->handle(attachPhoneInput());

        expect($callsInOrder)->toBe(['findForOwner', 'save']);
    });

    it('attaches a phone to any kind of owner', function (PhoneOwnerType $ownerType) {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with($ownerType, 'owner-1')->andReturnNull();
        $this->phones->shouldReceive('save')->once();

        expect($this->useCase->handle(attachPhoneInput($ownerType, 'owner-1'))->ownerType)
            ->toBe($ownerType);
    })->with([
        'business' => PhoneOwnerType::Business,
        'staff member' => PhoneOwnerType::StaffMember,
        'customer' => PhoneOwnerType::Customer,
    ]);

    it('stamps the phone with the injected clock, never with real time', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->phones->shouldReceive('save')->once();

        expect($this->useCase->handle(attachPhoneInput())->createdAt)
            ->toEqual(new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
    });
});

describe('when the owner already has a phone', function () {
    beforeEach(function () {
        $this->existing = Phone::restore(
            id: ATTACH_PHONE_EXISTING_ID,
            ownerType: PhoneOwnerType::Business,
            ownerId: 'business-1',
            number: PhoneNumbers::mexican('5500000000'),
            createdAt: new DateTimeImmutable('2025-06-15T09:30:00+00:00'),
        );
    });

    it('moves that record to the new number instead of inserting a second row', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Business, 'business-1')
            ->andReturn($this->existing);
        // The very entity that came back, carrying the new number: a different
        // instance here would mean a second row on the way to the database.
        $this->phones->shouldReceive('save')->once()
            ->with(Mockery::on(fn (Phone $phone): bool => $phone === $this->existing
                && $phone->number()->e164() === '+525512345678'));

        $data = $this->useCase->handle(attachPhoneInput());

        expect($data->number->e164())->toBe('+525512345678');
    });

    it('keeps the identity and the history of the record it found', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturn($this->existing);
        $this->phones->shouldReceive('save')->once();

        $data = $this->useCase->handle(attachPhoneInput());

        expect($data->id)->toBe(ATTACH_PHONE_EXISTING_ID)
            // A generated id reaching the output would mean a new record.
            ->and($data->id)->not->toBe(ATTACH_PHONE_GENERATED_ID)
            ->and($data->createdAt)->toEqual(new DateTimeImmutable('2025-06-15T09:30:00+00:00'))
            ->and($data->ownerType)->toBe(PhoneOwnerType::Business)
            ->and($data->ownerId)->toBe('business-1');
    });

    it('writes exactly once, however many times the owner changes their mind', function () {
        $this->phones->shouldReceive('findForOwner')->twice()->andReturn($this->existing);
        $this->phones->shouldReceive('save')->twice();

        $this->useCase->handle(attachPhoneInput(nationalNumber: '5512345678'));
        $final = $this->useCase->handle(attachPhoneInput(nationalNumber: '5587654321'));

        expect($final->id)->toBe(ATTACH_PHONE_EXISTING_ID)
            ->and($final->number->e164())->toBe('+525587654321');
    });

    it('accepts the number the owner already has without complaining', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturn($this->existing);
        $this->phones->shouldReceive('save')->once();

        expect($this->useCase->handle(attachPhoneInput(nationalNumber: '5500000000'))->number->e164())
            ->toBe('+525500000000');
    });
});

it('is still built from mocks alone, and asks for no parser', function () {
    // The bar the whole port exists to protect. Parsing needs a slab of
    // numbering-plan metadata and a geocoding locale; a use case that reached
    // for it would be a use case that needs a container to construct, and this
    // file would need one to run.
    //
    // beforeEach already built it from a mock and two fakes. What is asserted
    // here is that nothing in its constructor could ever need more than that.
    $types = array_map(
        static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
        (new ReflectionClass(AttachPhone::class))->getConstructor()->getParameters(),
    );

    expect($this->useCase)->toBeInstanceOf(AttachPhone::class)
        ->and($types)->not->toContain(PhoneNumberParser::class)
        ->and($types)->not->toBeEmpty();

    foreach ($types as $type) {
        expect(interface_exists($type))->toBeTrue("[{$type}] is a concretion, so this use case is no longer doubleable.");
    }
});

it('never deletes a phone on the way to attaching one', function () {
    $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
    $this->phones->shouldReceive('save')->once();
    $this->phones->shouldNotReceive('deleteForOwner');

    $this->useCase->handle(attachPhoneInput());
});
