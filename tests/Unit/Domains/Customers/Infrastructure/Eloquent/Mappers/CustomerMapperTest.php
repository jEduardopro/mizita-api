<?php

declare(strict_types=1);

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Infrastructure\Eloquent\Mappers\CustomerMapper;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use Tests\Support\FakeBusinessContext;

const CUSTOMER_MAPPER_BUSINESS_KEY = 42;

const CUSTOMER_MAPPER_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c1';

const CUSTOMER_MAPPER_OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

const CUSTOMER_MAPPER_NAME = 'Ada Lovelace';

const CUSTOMER_MAPPER_EMAIL = 'ada@example.com';

const CUSTOMER_MAPPER_BIRTH_DATE = '1990-05-04';

const CUSTOMER_MAPPER_NOTES = 'Prefiere la cita por la mañana.';

function customerMapperNow(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-01-01T12:00:00+00:00');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function mappedCustomerRow(array $overrides = []): CustomerModel
{
    $model = new CustomerModel;

    $model->setRawAttributes([
        'id' => 7,
        'uuid' => CUSTOMER_MAPPER_CUSTOMER_ID,
        'business_id' => CUSTOMER_MAPPER_BUSINESS_KEY,
        'name' => CUSTOMER_MAPPER_NAME,
        'email' => CUSTOMER_MAPPER_EMAIL,
        'birth_date' => CUSTOMER_MAPPER_BIRTH_DATE,
        'notes' => CUSTOMER_MAPPER_NOTES,
        'created_at' => customerMapperNow(),
        ...$overrides,
    ], true);

    return $model;
}

function mappedCustomer(
    string $id = CUSTOMER_MAPPER_CUSTOMER_ID,
    string $businessId = FakeBusinessContext::BUSINESS_ID,
    string $name = CUSTOMER_MAPPER_NAME,
    ?string $email = CUSTOMER_MAPPER_EMAIL,
    ?string $birthDate = CUSTOMER_MAPPER_BIRTH_DATE,
    ?string $notes = CUSTOMER_MAPPER_NOTES,
): Customer {
    return Customer::restore(
        id: $id,
        businessId: $businessId,
        name: $name,
        email: $email === null ? null : CustomerEmail::restore($email),
        birthDate: $birthDate === null ? null : new DateTimeImmutable($birthDate.' 00:00:00'),
        notes: $notes,
        createdAt: customerMapperNow(),
    );
}

beforeEach(function () {
    $this->mapper = new CustomerMapper;
});

describe('writing a row', function () {
    it('writes exactly the columns the row owns', function () {
        expect($this->mapper->toAttributes(mappedCustomer(), CUSTOMER_MAPPER_BUSINESS_KEY))->toBe([
            'uuid' => CUSTOMER_MAPPER_CUSTOMER_ID,
            'business_id' => CUSTOMER_MAPPER_BUSINESS_KEY,
            'name' => CUSTOMER_MAPPER_NAME,
            'email' => CUSTOMER_MAPPER_EMAIL,
            'birth_date' => CUSTOMER_MAPPER_BIRTH_DATE,
            'notes' => CUSTOMER_MAPPER_NOTES,
        ]);
    });

    it('writes the business as the int key it was handed, never as the uuid the entity carries', function () {
        $attributes = $this->mapper->toAttributes(mappedCustomer(), CUSTOMER_MAPPER_BUSINESS_KEY);

        expect($attributes['business_id'])->toBe(CUSTOMER_MAPPER_BUSINESS_KEY)
            ->toBeInt()
            ->and($attributes['business_id'])->not->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($attributes)->not->toContain(FakeBusinessContext::BUSINESS_ID);
    });

    it('writes the business key it was handed even when the entity belongs to another business', function () {
        $attributes = $this->mapper->toAttributes(
            mappedCustomer(businessId: CUSTOMER_MAPPER_OTHER_BUSINESS_ID),
            CUSTOMER_MAPPER_BUSINESS_KEY,
        );

        expect($attributes['business_id'])->toBe(CUSTOMER_MAPPER_BUSINESS_KEY);
    });

    it('writes the email as the plain string the column stores, not the value object', function () {
        expect($this->mapper->toAttributes(mappedCustomer(), CUSTOMER_MAPPER_BUSINESS_KEY)['email'])
            ->toBe(CUSTOMER_MAPPER_EMAIL)
            ->toBeString();
    });

    it('writes the birth date as the day the column holds, without a time', function () {
        expect($this->mapper->toAttributes(mappedCustomer(), CUSTOMER_MAPPER_BUSINESS_KEY)['birth_date'])
            ->toBe('1990-05-04');
    });

    it('writes null for every optional a customer left blank', function () {
        $attributes = $this->mapper->toAttributes(
            mappedCustomer(email: null, birthDate: null, notes: null),
            CUSTOMER_MAPPER_BUSINESS_KEY,
        );

        expect($attributes['email'])->toBeNull()
            ->and($attributes['birth_date'])->toBeNull()
            ->and($attributes['notes'])->toBeNull()
            ->and($attributes['name'])->toBe(CUSTOMER_MAPPER_NAME);
    });

    it('leaves the identity and the timestamps to the database', function () {
        $attributes = $this->mapper->toAttributes(mappedCustomer(), CUSTOMER_MAPPER_BUSINESS_KEY);

        expect($attributes)->not->toHaveKey('id')
            ->and($attributes)->not->toHaveKey('created_at')
            ->and($attributes)->not->toHaveKey('updated_at')
            ->and($attributes)->not->toHaveKey('deleted_at');
    });
});

describe('reading a row', function () {
    it('takes the identity from the uuid column, never from the primary key', function () {
        $customer = $this->mapper->toEntity(mappedCustomerRow(), FakeBusinessContext::BUSINESS_ID);

        expect($customer->id)->toBe(CUSTOMER_MAPPER_CUSTOMER_ID)
            ->and($customer->id)->not->toBe('7');
    });

    it('takes the business as the uuid it was handed, never the key the row holds', function () {
        $customer = $this->mapper->toEntity(mappedCustomerRow(), FakeBusinessContext::BUSINESS_ID);

        expect($customer->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->toBeString()
            ->and($customer->businessId)->not->toBe((string) CUSTOMER_MAPPER_BUSINESS_KEY)
            ->and(is_numeric($customer->businessId))->toBeFalse();
    });

    it('reads the same row under whichever business uuid it is handed', function () {
        $customer = $this->mapper->toEntity(mappedCustomerRow(), CUSTOMER_MAPPER_OTHER_BUSINESS_ID);

        expect($customer->businessId)->toBe(CUSTOMER_MAPPER_OTHER_BUSINESS_ID);
    });

    it('restores every value the row carries', function () {
        $customer = $this->mapper->toEntity(mappedCustomerRow(), FakeBusinessContext::BUSINESS_ID);

        expect($customer)->toBeInstanceOf(Customer::class)
            ->and($customer->name())->toBe(CUSTOMER_MAPPER_NAME)
            ->and($customer->email()?->value)->toBe(CUSTOMER_MAPPER_EMAIL)
            ->and($customer->birthDate()?->format('Y-m-d'))->toBe(CUSTOMER_MAPPER_BIRTH_DATE)
            ->and($customer->notes())->toBe(CUSTOMER_MAPPER_NOTES);
    });

    it('reads the email back as the value object the domain speaks', function () {
        expect($this->mapper->toEntity(mappedCustomerRow(), FakeBusinessContext::BUSINESS_ID)->email())
            ->toBeInstanceOf(CustomerEmail::class);
    });

    it('reads the birth date back as an immutable date at midnight', function () {
        $birthDate = $this->mapper->toEntity(mappedCustomerRow(), FakeBusinessContext::BUSINESS_ID)->birthDate();

        expect($birthDate)->toBeInstanceOf(DateTimeImmutable::class)
            ->and($birthDate->format('Y-m-d H:i:s'))->toBe('1990-05-04 00:00:00');
    });

    it('restores a row whose optionals were never filled in', function () {
        $customer = $this->mapper->toEntity(
            mappedCustomerRow(['email' => null, 'birth_date' => null, 'notes' => null]),
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($customer->email())->toBeNull()
            ->and($customer->birthDate())->toBeNull()
            ->and($customer->notes())->toBeNull()
            ->and($customer->name())->toBe(CUSTOMER_MAPPER_NAME);
    });

    it('restores a stored row without holding it to the invariants creation enforces', function () {
        $customer = $this->mapper->toEntity(
            mappedCustomerRow(['name' => '', 'email' => 'NOT an email', 'notes' => '   ']),
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($customer->name())->toBe('')
            ->and($customer->email()?->value)->toBe('NOT an email')
            ->and($customer->notes())->toBe('   ');
    });

    it('keeps the instant the row was created', function () {
        expect($this->mapper->toEntity(mappedCustomerRow(), FakeBusinessContext::BUSINESS_ID)->createdAt)
            ->toEqual(customerMapperNow());
    });

    it('keeps the accents a name was written with', function () {
        expect($this->mapper->toEntity(mappedCustomerRow(['name' => 'Begoña Muñiz']), FakeBusinessContext::BUSINESS_ID)
            ->name())->toBe('Begoña Muñiz');
    });
});

it('carries a customer through both directions unchanged', function () {
    $attributes = $this->mapper->toAttributes(mappedCustomer(), CUSTOMER_MAPPER_BUSINESS_KEY);

    $restored = $this->mapper->toEntity(
        mappedCustomerRow([...$attributes, 'created_at' => customerMapperNow()]),
        FakeBusinessContext::BUSINESS_ID,
    );

    expect($restored->id)->toBe(CUSTOMER_MAPPER_CUSTOMER_ID)
        ->and($restored->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($restored->name())->toBe(CUSTOMER_MAPPER_NAME)
        ->and($restored->email()?->value)->toBe(CUSTOMER_MAPPER_EMAIL)
        ->and($restored->birthDate()?->format('Y-m-d'))->toBe(CUSTOMER_MAPPER_BIRTH_DATE)
        ->and($restored->notes())->toBe(CUSTOMER_MAPPER_NOTES)
        ->and($restored->createdAt)->toEqual(customerMapperNow());
});

it('carries a customer who gave nothing but a name through both directions unchanged', function () {
    $customer = mappedCustomer(name: 'Begoña Muñiz', email: null, birthDate: null, notes: null);

    $attributes = $this->mapper->toAttributes($customer, CUSTOMER_MAPPER_BUSINESS_KEY);

    $restored = $this->mapper->toEntity(
        mappedCustomerRow([...$attributes, 'created_at' => customerMapperNow()]),
        FakeBusinessContext::BUSINESS_ID,
    );

    expect($restored->name())->toBe('Begoña Muñiz')
        ->and($restored->email())->toBeNull()
        ->and($restored->birthDate())->toBeNull()
        ->and($restored->notes())->toBeNull()
        ->and($restored->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
});
