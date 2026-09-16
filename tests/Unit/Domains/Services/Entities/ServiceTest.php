<?php

declare(strict_types=1);

use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\InvalidServiceDescription;
use App\Domains\Services\Exceptions\InvalidServiceName;
use App\Domains\Services\Exceptions\ServiceAlreadyActive;
use App\Domains\Services\Exceptions\ServiceAlreadyInactive;
use App\Domains\Services\Exceptions\ServiceRequiresStaff;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Domains\Services\ValueObjects\Buffer;
use App\Domains\Services\ValueObjects\Duration;
use App\Domains\Services\ValueObjects\Price;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Services\ValueObjects\Slug;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\ServiceFixtures;

/**
 * @param  list<string>  $staffIds
 */
function createService(
    string $name = ServiceFixtures::NAME,
    ?string $description = 'Incluye lavado.',
    bool $active = true,
    array $staffIds = [ServiceFixtures::STAFF_ID],
    string $businessId = FakeBusinessContext::BUSINESS_ID,
): Service {
    return Service::create(
        id: ServiceFixtures::SERVICE_ID,
        businessId: $businessId,
        name: $name,
        slug: Slug::restore(ServiceFixtures::SLUG),
        description: $description,
        duration: Duration::ofMinutes(45),
        buffer: Buffer::ofMinutes(10),
        price: Price::fromString('250'),
        color: ServiceColor::Teal,
        active: $active,
        staffIds: $staffIds,
        now: ServiceFixtures::now(),
    );
}

describe('creating a service', function () {
    it('holds everything it was given, scoped to the business that asked', function () {
        $service = createService();

        expect($service->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($service->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($service->name())->toBe(ServiceFixtures::NAME)
            ->and($service->slug())->toBe(ServiceFixtures::SLUG)
            ->and($service->description())->toBe('Incluye lavado.')
            ->and($service->durationMinutes())->toBe(45)
            ->and($service->bufferMinutes())->toBe(10)
            ->and($service->price())->toBe('250.00')
            ->and($service->color())->toBe(ServiceColor::Teal)
            ->and($service->isActive())->toBeTrue()
            ->and($service->staffIds())->toBe([ServiceFixtures::STAFF_ID])
            ->and($service->createdAt)->toEqual(ServiceFixtures::now());
    });

    it('trims the name it was handed', function () {
        expect(createService(name: "  Corte de pelo \t ")->name())->toBe('Corte de pelo');
    });

    it('keeps the accents of a name instead of folding them', function () {
        expect(createService(name: 'Barbería Ñandú')->name())->toBe('Barbería Ñandú');
    });

    it('rejects a name it cannot accept', function (string $name) {
        expect(fn () => createService(name: $name))->toThrow(InvalidServiceName::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'a single character' => 'A',
        'a single character padded' => '  A  ',
        'one past the maximum' => str_repeat('a', 121),
    ]);

    it('accepts a name at either end of what it allows', function (string $name) {
        expect(createService(name: $name)->name())->toBe($name);
    })->with([
        'the shortest' => 'Aa',
        'the longest' => str_repeat('a', 120),
        'the longest in accented characters' => str_repeat('ñ', 120),
    ]);

    it('takes no description at all', function () {
        expect(createService(description: null)->description())->toBeNull();
    });

    it('treats a blank description as none', function (string $description) {
        expect(createService(description: $description)->description())->toBeNull();
    })->with(['empty' => '', 'spaces' => '   ', 'newline' => "\n"]);

    it('trims a description it keeps', function () {
        expect(createService(description: '  Incluye lavado.  ')->description())->toBe('Incluye lavado.');
    });

    it('rejects a description longer than it stores', function () {
        expect(fn () => createService(description: str_repeat('a', 2001)))
            ->toThrow(InvalidServiceDescription::class);
    });

    it('accepts a description exactly as long as it stores', function () {
        expect(createService(description: str_repeat('a', 2000))->description())
            ->toHaveLength(2000);
    });

    it('can be created hidden', function () {
        expect(createService(active: false)->isActive())->toBeFalse();
    });

    it('drops the staff duplicates it was handed', function () {
        $service = createService(staffIds: [
            ServiceFixtures::STAFF_ID,
            ServiceFixtures::SECOND_STAFF_ID,
            ServiceFixtures::STAFF_ID,
        ]);

        expect($service->staffIds())->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('refuses to exist with nobody able to perform it', function () {
        expect(fn () => createService(staffIds: []))->toThrow(ServiceRequiresStaff::class);
    });

    it('accepts as many staff as it bounds', function () {
        $staffIds = array_map(static fn (int $n): string => "staff-{$n}", range(1, 50));

        expect(createService(staffIds: $staffIds)->staffIds())->toHaveCount(50);
    });

    it('refuses more staff than it bounds', function () {
        $staffIds = array_map(static fn (int $n): string => "staff-{$n}", range(1, 51));

        expect(fn () => createService(staffIds: $staffIds))->toThrow(UnknownStaffMember::class);
    });

    it('counts the staff it bounds after dropping the duplicates', function () {
        $staffIds = array_map(static fn (int $n): string => 'staff-'.($n % 50), range(1, 120));

        expect(createService(staffIds: $staffIds)->staffIds())->toHaveCount(50);
    });
});

describe('restoring a service', function () {
    it('skips the invariants creation enforces', function () {
        $service = ServiceFixtures::service(name: '', description: str_repeat('a', 5000));

        expect($service->name())->toBe('')
            ->and($service->description())->toHaveLength(5000);
    });

    it('takes back a row saved before a service was required to have staff', function () {
        expect(ServiceFixtures::service(staffIds: [])->staffIds())->toBe([]);
    });

    it('keeps the staff exactly as persistence handed them over', function () {
        $service = ServiceFixtures::service(staffIds: [
            ServiceFixtures::STAFF_ID,
            ServiceFixtures::STAFF_ID,
        ]);

        expect($service->staffIds())->toHaveCount(2);
    });
});

describe('changing a service', function () {
    it('renames itself and takes the address it was handed', function () {
        $service = ServiceFixtures::service();

        $service->rename('  Corte premium  ', Slug::restore('corte-premium'));

        expect($service->name())->toBe('Corte premium')
            ->and($service->slug())->toBe('corte-premium');
    });

    it('refuses a rename to a name it would not have accepted', function (string $name) {
        $service = ServiceFixtures::service();

        expect(fn () => $service->rename($name, Slug::restore('whatever')))
            ->toThrow(InvalidServiceName::class)
            ->and($service->name())->toBe(ServiceFixtures::NAME)
            ->and($service->slug())->toBe(ServiceFixtures::SLUG);
    })->with(['empty' => '  ', 'too short' => 'A', 'too long' => str_repeat('a', 121)]);

    it('redescribes itself, blank included', function () {
        $service = ServiceFixtures::service();

        $service->redescribe('  Nueva descripción  ');
        expect($service->description())->toBe('Nueva descripción');

        $service->redescribe('   ');
        expect($service->description())->toBeNull();

        $service->redescribe(null);
        expect($service->description())->toBeNull();
    });

    it('refuses a description longer than it stores', function () {
        $service = ServiceFixtures::service();

        expect(fn () => $service->redescribe(str_repeat('a', 2001)))
            ->toThrow(InvalidServiceDescription::class)
            ->and($service->description())->toBe('Incluye lavado.');
    });

    it('reschedules its duration and buffer together', function () {
        $service = ServiceFixtures::service();

        $service->reschedule(Duration::ofMinutes(90), Buffer::none());

        expect($service->durationMinutes())->toBe(90)
            ->and($service->bufferMinutes())->toBe(0);
    });

    it('reprices itself', function () {
        $service = ServiceFixtures::service();

        $service->reprice(Price::free());

        expect($service->price())->toBe('0.00');
    });

    it('recolors itself', function () {
        $service = ServiceFixtures::service();

        $service->recolor(ServiceColor::Sand);

        expect($service->color())->toBe(ServiceColor::Sand);
    });

    it('reassigns its staff, dropping duplicates', function () {
        $service = ServiceFixtures::service();

        $service->assignStaff([
            ServiceFixtures::SECOND_STAFF_ID,
            ServiceFixtures::SECOND_STAFF_ID,
            ServiceFixtures::STAFF_ID,
        ]);

        expect($service->staffIds())->toBe([ServiceFixtures::SECOND_STAFF_ID, ServiceFixtures::STAFF_ID]);
    });

    it('refuses to be left with nobody to perform it, and keeps the staff it had', function () {
        $service = ServiceFixtures::service();

        expect(fn () => $service->assignStaff([]))->toThrow(ServiceRequiresStaff::class)
            ->and($service->staffIds())->toBe([ServiceFixtures::STAFF_ID]);
    });

    it('refuses more staff than it bounds and keeps the ones it had', function () {
        $service = ServiceFixtures::service();

        expect(fn () => $service->assignStaff(array_map(
            static fn (int $n): string => "staff-{$n}",
            range(1, 51),
        )))->toThrow(UnknownStaffMember::class)
            ->and($service->staffIds())->toBe([ServiceFixtures::STAFF_ID]);
    });
});

describe('publishing a service', function () {
    it('activates a hidden service', function () {
        $service = ServiceFixtures::service(active: false);

        $service->activate();

        expect($service->isActive())->toBeTrue();
    });

    it('deactivates a visible service', function () {
        $service = ServiceFixtures::service(active: true);

        $service->deactivate();

        expect($service->isActive())->toBeFalse();
    });

    it('refuses to activate a service that is already active', function () {
        $service = ServiceFixtures::service(active: true);

        expect(fn () => $service->activate())
            ->toThrow(ServiceAlreadyActive::class, ServiceFixtures::SERVICE_ID)
            ->and($service->isActive())->toBeTrue();
    });

    it('refuses to deactivate a service that is already hidden', function () {
        $service = ServiceFixtures::service(active: false);

        expect(fn () => $service->deactivate())
            ->toThrow(ServiceAlreadyInactive::class, ServiceFixtures::SERVICE_ID)
            ->and($service->isActive())->toBeFalse();
    });
});

describe('duplicating a service', function () {
    it('copies every value but its identity, its name, its address and its date', function () {
        $original = ServiceFixtures::service(
            description: 'Incluye lavado.',
            durationMinutes: 90,
            bufferMinutes: 15,
            price: '400.00',
            color: ServiceColor::Amber,
            active: true,
            staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID],
        );

        $copy = $original->duplicateAs(
            id: ServiceFixtures::GENERATED_SERVICE_ID,
            name: 'Corte de pelo (Copy)',
            slug: Slug::restore('corte-de-pelo-copy'),
            now: new DateTimeImmutable('2026-03-29T10:00:00+00:00'),
        );

        expect($copy->id)->toBe(ServiceFixtures::GENERATED_SERVICE_ID)
            ->and($copy->name())->toBe('Corte de pelo (Copy)')
            ->and($copy->slug())->toBe('corte-de-pelo-copy')
            ->and($copy->createdAt)->toEqual(new DateTimeImmutable('2026-03-29T10:00:00+00:00'))
            ->and($copy->businessId)->toBe($original->businessId)
            ->and($copy->description())->toBe('Incluye lavado.')
            ->and($copy->durationMinutes())->toBe(90)
            ->and($copy->bufferMinutes())->toBe(15)
            ->and($copy->price())->toBe('400.00')
            ->and($copy->color())->toBe(ServiceColor::Amber)
            ->and($copy->staffIds())->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('starts the copy hidden however visible the original is', function (bool $active) {
        $copy = ServiceFixtures::service(active: $active)->duplicateAs(
            id: ServiceFixtures::GENERATED_SERVICE_ID,
            name: 'Corte de pelo (Copy)',
            slug: Slug::restore('corte-de-pelo-copy'),
            now: ServiceFixtures::now(),
        );

        expect($copy->isActive())->toBeFalse();
    })->with(['a visible original' => true, 'a hidden original' => false]);

    it('leaves the original untouched', function () {
        $original = ServiceFixtures::service(active: true);

        $original->duplicateAs(
            id: ServiceFixtures::GENERATED_SERVICE_ID,
            name: 'Otro nombre',
            slug: Slug::restore('otro-nombre'),
            now: ServiceFixtures::now(),
        );

        expect($original->name())->toBe(ServiceFixtures::NAME)
            ->and($original->slug())->toBe(ServiceFixtures::SLUG)
            ->and($original->isActive())->toBeTrue();
    });

    it('holds the copy to the name invariant', function (string $name) {
        expect(fn () => ServiceFixtures::service()->duplicateAs(
            id: ServiceFixtures::GENERATED_SERVICE_ID,
            name: $name,
            slug: Slug::restore('whatever'),
            now: ServiceFixtures::now(),
        ))->toThrow(InvalidServiceName::class);
    })->with(['empty' => '   ', 'too short' => 'A', 'too long' => str_repeat('a', 121)]);
});
