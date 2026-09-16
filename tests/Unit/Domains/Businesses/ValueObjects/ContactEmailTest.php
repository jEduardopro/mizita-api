<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\InvalidBusinessContactEmail;
use App\Domains\Businesses\ValueObjects\ContactEmail;

describe('accepting an address', function () {
    it('keeps a well formed address as it arrived', function () {
        expect(ContactEmail::fromString('hola@barberia.com')->value)->toBe('hola@barberia.com');
    });

    it('trims what the caller padded', function (string $padded) {
        expect(ContactEmail::fromString($padded)->value)->toBe('hola@barberia.com');
    })->with([
        'spaces' => '  hola@barberia.com  ',
        'tab' => "\thola@barberia.com",
        'newline' => "hola@barberia.com\n",
    ]);

    it('accepts an address at a subdomain', function () {
        expect(ContactEmail::fromString('hola@mail.barberia.com')->value)->toBe('hola@mail.barberia.com');
    });

    it('accepts a plus addressed mailbox', function () {
        expect(ContactEmail::fromString('hola+citas@barberia.com')->value)->toBe('hola+citas@barberia.com');
    });
});

describe('normalising the domain', function () {
    it('folds the domain to lower case, because a host is case insensitive', function (string $submitted, string $expected) {
        expect(ContactEmail::fromString($submitted)->value)->toBe($expected);
    })->with([
        'upper case domain' => ['hola@BARBERIA.COM', 'hola@barberia.com'],
        'mixed case domain' => ['hola@Barberia.Com', 'hola@barberia.com'],
        'upper case subdomain' => ['hola@MAIL.Barberia.com', 'hola@mail.barberia.com'],
    ]);

    it('preserves the case of the local part, which a mail server may treat as significant', function () {
        expect(ContactEmail::fromString('Ada.Lovelace@Example.COM')->value)->toBe('Ada.Lovelace@example.com');
    });

    it('folds the domain of an address that was also padded', function () {
        expect(ContactEmail::fromString('   Ada@EXAMPLE.com   ')->value)->toBe('Ada@example.com');
    });
});

describe('refusing an address', function () {
    it('refuses an empty address', function (string $value) {
        expect(fn () => ContactEmail::fromString($value))
            ->toThrow(InvalidBusinessContactEmail::class, 'A business contact email cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'mixed whitespace' => " \t\n ",
    ]);

    it('refuses anything that is not an address at all', function (string $value) {
        expect(fn () => ContactEmail::fromString($value))
            ->toThrow(InvalidBusinessContactEmail::class, 'That is not a well formed business contact email.');
    })->with([
        'no at sign' => 'hola.barberia.com',
        'no domain' => 'hola@',
        'no local part' => '@barberia.com',
        'two at signs' => 'hola@@barberia.com',
        'a space inside' => 'hola barberia@example.com',
        'no top level domain' => 'hola@barberia',
        'an accented domain' => 'hola@barberiañandu.com',
        'a bare word' => 'hola',
    ]);

    it('refuses an address longer than the column holds', function () {
        expect(fn () => ContactEmail::fromString(str_repeat('a', 250).'@barberia.com'))
            ->toThrow(InvalidBusinessContactEmail::class, 'A business contact email cannot be longer than 255 characters.');
    });

    it('reports the length before the shape, so a huge payload is refused without being parsed', function () {
        expect(fn () => ContactEmail::fromString(str_repeat('a', 300)))
            ->toThrow(InvalidBusinessContactEmail::class, 'A business contact email cannot be longer than 255 characters.');
    });

    it('measures characters rather than bytes', function () {
        expect(fn () => ContactEmail::fromString(str_repeat('é', 200).'@barberia.com'))
            ->toThrow(InvalidBusinessContactEmail::class, 'That is not a well formed business contact email.');
    });
});

describe('rehydrating from storage', function () {
    it('accepts a stored value fromString would refuse', function (string $stored) {
        expect(ContactEmail::restore($stored)->value)->toBe($stored);
    })->with([
        'an address that stopped being valid' => 'hola@barberia',
        'an upper case domain written before normalising existed' => 'hola@BARBERIA.COM',
        'empty' => '',
    ]);
});

describe('equality', function () {
    it('compares by value', function () {
        expect(ContactEmail::fromString('hola@barberia.com')->equals(ContactEmail::fromString('hola@barberia.com')))
            ->toBeTrue()
            ->and(ContactEmail::fromString('hola@barberia.com')->equals(ContactEmail::fromString('otro@barberia.com')))
            ->toBeFalse();
    });

    it('treats two spellings of the same domain as one address', function () {
        expect(ContactEmail::fromString('hola@BARBERIA.com')->equals(ContactEmail::fromString('hola@barberia.com')))
            ->toBeTrue();
    });

    it('treats two spellings of the same local part as different addresses', function () {
        expect(ContactEmail::fromString('Hola@barberia.com')->equals(ContactEmail::fromString('hola@barberia.com')))
            ->toBeFalse();
    });
});
