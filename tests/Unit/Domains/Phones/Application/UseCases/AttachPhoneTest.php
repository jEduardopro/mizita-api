<?php

declare(strict_types=1);

use App\Domains\Phones\Application\Dtos\AttachPhoneInput;
use App\Domains\Phones\Application\Dtos\PhoneData;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\PhoneNumberParser;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\PhoneNumbers;

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

        $data = $this->useCase->handle(attachPhoneInput())->value();

        expect($data)->toBeInstanceOf(PhoneData::class)
            ->and($data->id)->toBe(ATTACH_PHONE_GENERATED_ID)
            ->and($data->ownerType)->toBe(PhoneOwnerType::Business)
            ->and($data->ownerId)->toBe('business-1')
            ->and($data->number->e164())->toBe('+525512345678')
            ->and($data->createdAt)->toEqual(attachPhoneNow());
    });

    it('asks whether the owner already has one before writing anything', function () {
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

        expect($this->useCase->handle(attachPhoneInput($ownerType, 'owner-1'))->value()->ownerType)
            ->toBe($ownerType);
    })->with([
        'business' => PhoneOwnerType::Business,
        'staff member' => PhoneOwnerType::StaffMember,
    ]);

    it('stamps the phone with the injected clock, never with real time', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->phones->shouldReceive('save')->once();

        expect($this->useCase->handle(attachPhoneInput())->value()->createdAt)
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
        $this->phones->shouldReceive('save')->once()
            ->with(Mockery::on(fn (Phone $phone): bool => $phone === $this->existing
                && $phone->number()->e164() === '+525512345678'));

        $data = $this->useCase->handle(attachPhoneInput())->value();

        expect($data->number->e164())->toBe('+525512345678');
    });

    it('keeps the identity and the history of the record it found', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturn($this->existing);
        $this->phones->shouldReceive('save')->once();

        $data = $this->useCase->handle(attachPhoneInput())->value();

        expect($data->id)->toBe(ATTACH_PHONE_EXISTING_ID)
            ->and($data->id)->not->toBe(ATTACH_PHONE_GENERATED_ID)
            ->and($data->createdAt)->toEqual(new DateTimeImmutable('2025-06-15T09:30:00+00:00'))
            ->and($data->ownerType)->toBe(PhoneOwnerType::Business)
            ->and($data->ownerId)->toBe('business-1');
    });

    it('writes exactly once, however many times the owner changes their mind', function () {
        $this->phones->shouldReceive('findForOwner')->twice()->andReturn($this->existing);
        $this->phones->shouldReceive('save')->twice();

        $this->useCase->handle(attachPhoneInput(nationalNumber: '5512345678'));
        $final = $this->useCase->handle(attachPhoneInput(nationalNumber: '5587654321'))->value();

        expect($final->id)->toBe(ATTACH_PHONE_EXISTING_ID)
            ->and($final->number->e164())->toBe('+525587654321');
    });

    it('accepts the number the owner already has without complaining', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturn($this->existing);
        $this->phones->shouldReceive('save')->once();

        expect($this->useCase->handle(attachPhoneInput(nationalNumber: '5500000000'))->value()->number->e164())
            ->toBe('+525500000000');
    });
});

describe('the response it hands back', function () {
    it('reports success and carries no warning', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->phones->shouldReceive('save')->once();

        $response = $this->useCase->handle(attachPhoneInput());

        expect($response)->toBeInstanceOf(UseCaseResponse::class)
            ->and($response->succeeded())->toBeTrue()
            ->and($response->failed())->toBeFalse()
            ->and($response->warnings())->toBe([])
            ->and($response->value())->toBeInstanceOf(PhoneData::class);
    });

    it('lets a lookup failure escape rather than dressing it as a refusal', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->andThrow(new RuntimeException('SQLSTATE[08006] connection failure'));
        $this->phones->shouldNotReceive('save');

        expect(fn () => $this->useCase->handle(attachPhoneInput()))
            ->toThrow(RuntimeException::class, 'SQLSTATE[08006] connection failure');
    });

    it('lets a write failure escape rather than dressing it as a refusal', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->phones->shouldReceive('save')->once()
            ->andThrow(new RuntimeException('SQLSTATE[23505] duplicate key'));

        expect(fn () => $this->useCase->handle(attachPhoneInput()))
            ->toThrow(RuntimeException::class, 'SQLSTATE[23505] duplicate key');
    });
});

it('is still built from mocks alone, and asks for no parser', function () {
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
