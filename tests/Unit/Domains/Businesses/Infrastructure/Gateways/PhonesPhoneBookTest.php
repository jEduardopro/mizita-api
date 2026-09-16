<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Infrastructure\Gateways\PhonesPhoneBook;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Exceptions\UseCaseFailed;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\PhoneNumber;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->attachedPhoneId = '01930000-0000-7000-8000-0000000000d1';
    $this->phones = Mockery::mock(PhoneRepository::class);

    $this->phoneBook = new PhonesPhoneBook(
        new AttachPhone(
            $this->phones,
            new FixedIdGenerator($this->attachedPhoneId),
            new FakeClock(OnboardingFixtures::now()),
        ),
        $this->phones,
    );

    $this->attach = fn (): mixed => $this->phoneBook->attachToBusiness(
        OnboardingFixtures::GENERATED_BUSINESS_ID,
        OnboardingFixtures::phone(),
    );

    $this->read = fn (): ?PhoneNumber => $this->phoneBook->forBusiness(OnboardingFixtures::GENERATED_BUSINESS_ID);

    $this->replace = fn (?PhoneNumber $phone): mixed => $this->phoneBook->replaceForBusiness(
        OnboardingFixtures::GENERATED_BUSINESS_ID,
        $phone,
    );
});

describe('reading the number on file', function () {
    it('answers with nothing when the business filed no number', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Business, OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->andReturnNull();

        expect(($this->read)())->toBeNull();
    });

    it('hands back the number itself, not the row that holds it', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturn(Phone::restore(
            id: $this->attachedPhoneId,
            ownerType: PhoneOwnerType::Business,
            ownerId: OnboardingFixtures::GENERATED_BUSINESS_ID,
            number: OnboardingFixtures::phone(),
            createdAt: OnboardingFixtures::now(),
        ));

        $number = ($this->read)();

        expect($number)->toBeInstanceOf(PhoneNumber::class)
            ->and($number->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($number->country())->toBe(CountryCode::Mx);
    });

    it('asks for the number under the business uuid, never an internal key', function () {
        $ownerId = null;

        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Business, Mockery::capture($ownerId))
            ->andReturnNull();

        ($this->read)();

        expect($ownerId)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->toBeString()
            ->and(is_numeric($ownerId))->toBeFalse();
    });
});

describe('replacing the number a business submitted', function () {
    it('deletes the number the business had when it submits none', function () {
        $this->phones->shouldReceive('deleteForOwner')->once()
            ->with(PhoneOwnerType::Business, OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->phones->shouldNotReceive('save');

        expect(($this->replace)(null))->toBeNull();
    });

    it('files the submitted number without deleting anything', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->phones->shouldReceive('save')->once();
        $this->phones->shouldNotReceive('deleteForOwner');

        ($this->replace)(OnboardingFixtures::phone());
    });

    it('updates the number the business already had rather than filing a second one', function () {
        $existing = Phone::restore(
            id: $this->attachedPhoneId,
            ownerType: PhoneOwnerType::Business,
            ownerId: OnboardingFixtures::GENERATED_BUSINESS_ID,
            number: OnboardingFixtures::phone(),
            createdAt: OnboardingFixtures::now(),
        );

        $this->phones->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->phones->shouldReceive('save')->once()->with($existing);

        ($this->replace)(OnboardingFixtures::phone(CountryCode::Us, PhoneNumbers::US_NATIONAL_NUMBER));

        expect($existing->id)->toBe($this->attachedPhoneId)
            ->and($existing->number()->e164())->toBe(PhoneNumbers::US_E164);
    });

    it('lets a refusal out by throwing when the number cannot be filed', function () {
        $failure = UseCaseFailed::with('phone_write_refused', DomainFailureKind::Conflict);

        $this->phones->shouldReceive('findForOwner')->andReturnNull();
        $this->phones->shouldReceive('save')->once()->andThrow($failure);

        try {
            ($this->replace)(OnboardingFixtures::phone());
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBe($failure);
    });

    it('lets a repository error out untouched when it deletes', function () {
        $bug = new RuntimeException('the phones table is gone');

        $this->phones->shouldReceive('deleteForOwner')->once()->andThrow($bug);

        expect(fn () => ($this->replace)(null))->toThrow($bug);
    });
});

describe('filing a business number', function () {
    it('files the number against the business, as a business number', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Business, OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->andReturnNull();

        $saved = null;
        $this->phones->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->attach)();

        expect($saved)->toBeInstanceOf(Phone::class)
            ->and($saved->ownerType)->toBe(PhoneOwnerType::Business)
            ->and($saved->ownerId)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($saved->number()->equals(OnboardingFixtures::phone()))->toBeTrue();
    });

    it('replaces the number the business already had rather than filing a second one', function () {
        $existing = Phone::restore(
            id: $this->attachedPhoneId,
            ownerType: PhoneOwnerType::Business,
            ownerId: OnboardingFixtures::GENERATED_BUSINESS_ID,
            number: OnboardingFixtures::phone(),
            createdAt: OnboardingFixtures::now(),
        );

        $this->phones->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->phones->shouldReceive('save')->once()->with($existing);

        ($this->attach)();

        expect($existing->number()->equals(OnboardingFixtures::phone()))->toBeTrue();
    });
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        $this->phones->shouldReceive('findForOwner')->andReturnNull();
        $this->phones->shouldReceive('save')->once();

        expect(($this->attach)())->toBeNull();
    });

    it('never declares a use case response on the port, which is what forbids answering with a failure', function () {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass(PhoneBook::class))->getMethods(),
        );

        expect($returnTypes)->not->toContain(UseCaseResponse::class)
            ->and($returnTypes)->not->toContain('?'.UseCaseResponse::class);
    });

    it('declares void on every write the port exposes', function (string $method) {
        $returnType = (new ReflectionMethod(PhoneBook::class, $method))->getReturnType();

        expect((string) $returnType)->toBe('void');
    })->with(['attachToBusiness', 'replaceForBusiness']);

    it('lets a domain failure out by throwing, so the surrounding transaction rolls back', function () {
        $failure = UseCaseFailed::with('phone_write_refused', DomainFailureKind::Conflict);

        $this->phones->shouldReceive('findForOwner')->andReturnNull();
        $this->phones->shouldReceive('save')->once()->andThrow($failure);

        try {
            ($this->attach)();
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBe($failure);
    });

    it('lets an infrastructure error out untouched', function () {
        $bug = new RuntimeException('the phones table is gone');

        $this->phones->shouldReceive('findForOwner')->andReturnNull();
        $this->phones->shouldReceive('save')->once()->andThrow($bug);

        expect(fn () => ($this->attach)())->toThrow($bug);
    });

    it('does not write when the read it depends on fails', function () {
        $bug = new RuntimeException('the read replica went away');

        $this->phones->shouldReceive('findForOwner')->once()->andThrow($bug);
        $this->phones->shouldNotReceive('save');

        expect(fn () => ($this->attach)())->toThrow($bug);
    });
});
