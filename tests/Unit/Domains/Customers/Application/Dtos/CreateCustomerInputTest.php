<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CreateCustomerInput;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Shared\Contracts\DomainFailure;

it('resolves every field the form request lets through', function () {
    $input = CreateCustomerInput::fromRequest([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '5512345678',
    ]);

    expect($input->name)->toBe('Ada Lovelace')
        ->and($input->email)->toBe('ada@example.com')
        ->and($input->phone)->toBe('5512345678');
});

it('reads an optional field the client omitted as absent', function (array $payload) {
    $input = CreateCustomerInput::fromRequest(['name' => 'Ada Lovelace', ...$payload]);

    expect($input->email)->toBeNull()
        ->and($input->phone)->toBeNull();
})->with([
    'the keys are absent' => [[]],
    'the keys are null' => [['email' => null, 'phone' => null]],
    'the keys are empty strings' => [['email' => '', 'phone' => '']],
]);

it('carries the name untouched, because the entity is what rules on it', function () {
    expect(CreateCustomerInput::fromRequest(['name' => '   '])->name)->toBe('   ');
});

it('ignores a tenant the client tried to choose for itself', function () {
    $input = CreateCustomerInput::fromRequest([
        'name' => 'Ada Lovelace',
        'business_id' => 'another-business-uuid',
    ]);

    expect($input)->toEqual(CreateCustomerInput::fromRequest(['name' => 'Ada Lovelace']));
});

describe('validating the customer payload', function () {
    it('accepts a well formed customer', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '5512345678',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a customer with neither an optional email nor an optional phone', function () {
        expect(fn () => CreateCustomerInput::fromRequest(['name' => 'Ada Lovelace'])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a blank name', function (string $name) {
        expect(fn () => (new CreateCustomerInput($name, null, null))->validate())
            ->toThrow(InvalidCustomerName::class, 'A customer name cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'a newline' => "\n",
    ]);

    it('refuses a name longer than the column holds', function () {
        expect(fn () => (new CreateCustomerInput(str_repeat('a', 256), null, null))->validate())
            ->toThrow(InvalidCustomerName::class, 'The name offered is longer than a customer name may be.');
    });

    it('accepts a name at the maximum length', function (string $name) {
        expect(fn () => (new CreateCustomerInput($name, null, null))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'the longest allowed' => str_repeat('a', 255),
        'the longest allowed in accented characters' => str_repeat('ñ', 255),
        'the longest allowed once the padding is trimmed' => '  '.str_repeat('a', 255).'  ',
    ]);

    it('refuses an address that is not plausibly an email', function (string $email) {
        expect(fn () => (new CreateCustomerInput('Ada Lovelace', $email, null))->validate())
            ->toThrow(InvalidCustomerEmail::class, 'The address offered is not a valid email address.');
    })->with([
        'no at sign' => 'ada.example.com',
        'no domain' => 'ada@',
        'no local part' => '@example.com',
        'a space inside' => 'ada lovelace@example.com',
        'two at signs' => 'ada@@example.com',
        'whitespace only' => '   ',
    ]);

    it('refuses an address longer than the column holds', function (string $email) {
        expect(fn () => (new CreateCustomerInput('Ada Lovelace', $email, null))->validate())
            ->toThrow(InvalidCustomerEmail::class, 'The address offered is longer than a customer email may be.');
    })->with([
        'longer than an address may be' => str_repeat('a', 250).'@example.com',
        'one character beyond the maximum' => str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('b', 63).'.'.str_repeat('b', 58).'.com',
    ]);

    it('weighs the length before the shape, because the format check cannot rule past the bound', function () {
        expect(fn () => (new CreateCustomerInput('Ada Lovelace', str_repeat('a', 255), null))->validate())
            ->toThrow(InvalidCustomerEmail::class, 'The address offered is longer than a customer email may be.');
    });

    it('accepts an unusual but well formed address', function (string $email) {
        expect(fn () => (new CreateCustomerInput('Ada Lovelace', $email, null))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'a plus tag' => 'ada+bookings@example.com',
        'a subdomain' => 'ada@mail.example.co.uk',
        'the longest address PHP accepts' => str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('b', 63).'.'.str_repeat('b', 57).'.com',
    ]);

    it('refuses a phone longer than the column holds', function () {
        expect(fn () => (new CreateCustomerInput('Ada Lovelace', null, str_repeat('5', 256)))->validate())
            ->toThrow(InvalidCustomerPhone::class, 'The number offered is longer than a customer phone may be.');
    });

    it('accepts a phone at the maximum length', function () {
        expect(fn () => (new CreateCustomerInput('Ada Lovelace', null, str_repeat('5', 255)))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('leaves the shape of a phone to the domain that parses one', function (string $phone) {
        expect(fn () => (new CreateCustomerInput('Ada Lovelace', null, $phone))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'separators' => ' (55) 1234-5678 ',
        'an international prefix' => '+52 55 1234 5678',
        'not a number at all' => 'call the shop',
    ]);

    it('reports the name first when the payload is wrong in several ways at once', function () {
        expect(fn () => (new CreateCustomerInput('', 'not-an-email', str_repeat('5', 256)))->validate())
            ->toThrow(InvalidCustomerName::class);
    });

    it('refuses a payload with every key missing as a domain failure, not a PHP error', function () {
        $thrown = null;

        try {
            CreateCustomerInput::fromRequest([])->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(InvalidCustomerName::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class);
    });

    it('classifies a refused email as a domain failure the edge can render', function () {
        $thrown = null;

        try {
            (new CreateCustomerInput('Ada Lovelace', 'not-an-email', null))->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class);
    });

    it('classifies a refused phone as a domain failure the edge can render', function () {
        $thrown = null;

        try {
            (new CreateCustomerInput('Ada Lovelace', null, str_repeat('5', 256)))->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class);
    });
});

it('refuses a numeric optional field as a domain failure rather than a PHP error', function () {
    $thrown = null;

    try {
        CreateCustomerInput::fromRequest(['name' => 'Ada Lovelace', 'phone' => 5512345678]);
    } catch (Throwable $failure) {
        $thrown = $failure;
    }

    expect($thrown)->toBeInstanceOf(InvalidCustomerPhone::class)
        ->and($thrown)->toBeInstanceOf(DomainFailure::class)
        ->and($thrown->getMessage())->toBe('The number offered is not a valid customer phone.');
});

it('refuses an unreadable optional field the moment the payload is read, rather than discarding it', function (array $payload, string $failure) {
    expect(fn () => CreateCustomerInput::fromRequest(['name' => 'Ada Lovelace', ...$payload]))
        ->toThrow($failure);
})->with([
    'a numeric phone' => [['phone' => 5512345678], InvalidCustomerPhone::class],
    'a float phone' => [['phone' => 55.12], InvalidCustomerPhone::class],
    'a phone sent as an object' => [['phone' => ['country_code' => 'MX']], InvalidCustomerPhone::class],
    'a phone sent as a boolean' => [['phone' => true], InvalidCustomerPhone::class],
    'a numeric email' => [['email' => 5512345678], InvalidCustomerEmail::class],
    'an email sent as an array' => [['email' => ['ada@example.com']], InvalidCustomerEmail::class],
]);

it('names the field it could not read, rather than blaming its neighbour', function () {
    expect(fn () => CreateCustomerInput::fromRequest([
        'name' => 'Ada Lovelace',
        'email' => ['ada@example.com'],
        'phone' => 5512345678,
    ]))->toThrow(InvalidCustomerEmail::class);
});

it('keeps an optional field whose value is the string zero', function () {
    expect(CreateCustomerInput::fromRequest(['name' => 'Ada Lovelace', 'phone' => '0'])->phone)->toBe('0');
});

it('keeps an email whose value is the string zero, however unlikely, rather than reading it as absent', function () {
    expect(CreateCustomerInput::fromRequest(['name' => 'Ada Lovelace', 'email' => '0'])->email)->toBe('0');
});

it('reads a name that is not a string as absent, and refuses it as a name', function (mixed $name) {
    expect(fn () => CreateCustomerInput::fromRequest(['name' => $name])->validate())
        ->toThrow(InvalidCustomerName::class, 'A customer name cannot be empty.');
})->with([
    'an array' => [['Ada Lovelace']],
    'an integer' => 42,
    'a boolean' => true,
    'null' => null,
]);
