<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\BusinessNameNotSluggable;
use App\Domains\Businesses\Exceptions\InvalidBusinessSlug;
use App\Domains\Businesses\ValueObjects\Slug;

describe('normalising a name', function () {
    it('turns a name into the address it will publish', function (string $name, string $expected) {
        expect(Slug::fromName($name)->value)->toBe($expected);
    })->with([
        'plain' => ['Barberia Nandu', 'barberia-nandu'],
        'spanish accents and enye' => ['Barbería Ñandú', 'barberia-nandu'],
        'folds case' => ['BARBERÍA ÑANDÚ', 'barberia-nandu'],
        'french and portuguese accents' => ['Coiffeur Crème Brûlée São', 'coiffeur-creme-brulee-sao'],
        'ligatures and eszett' => ['Straße Æon Œuvre', 'strasse-aeon-oeuvre'],
        'digits survive' => ['Studio 54', 'studio-54'],
    ]);

    it('collapses every run of separators into exactly one hyphen', function (string $name, string $expected) {
        expect(Slug::fromName($name)->value)->toBe($expected);
    })->with([
        'repeated spaces' => ['Salón   de   Belleza', 'salon-de-belleza'],
        'punctuation' => ['Café & Té, S.L.', 'cafe-te-s-l'],
        'typed hyphens' => ['Barberia -- Nandu', 'barberia-nandu'],
        'tabs and newlines' => ["Salon\tde\nBelleza", 'salon-de-belleza'],
        'underscores' => ['salon_de_belleza', 'salon-de-belleza'],
    ]);

    it('leaves no hyphen at either end', function (string $name, string $expected) {
        expect(Slug::fromName($name)->value)->toBe($expected)
            ->and($expected)->not->toStartWith('-')
            ->and($expected)->not->toEndWith('-');
    })->with([
        'leading and trailing punctuation' => ['--Peluquería--', 'peluqueria'],
        'surrounding whitespace' => ['   Peluqueria   ', 'peluqueria'],
        'trailing separator only' => ['Peluqueria!', 'peluqueria'],
    ]);

    it('drops what the transliteration table does not cover instead of mangling it', function () {
        expect(Slug::fromName('Salon 北京')->value)->toBe('salon');
    });
});

describe('names that produce no address', function () {
    it('refuses a name that leaves nothing behind', function (string $name) {
        expect(fn () => Slug::fromName($name))
            ->toThrow(BusinessNameNotSluggable::class, "The name [{$name}] does not produce a usable slug.");
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'punctuation only' => '!!! ???',
        'a script the table does not cover' => '北京 沙龙',
        'emoji only' => '💇',
    ]);

    it('refuses every reserved word, whatever case it was typed in', function (string $reserved) {
        expect(fn () => Slug::fromName($reserved))->toThrow(BusinessNameNotSluggable::class)
            ->and(Slug::tryFromName(strtoupper($reserved)))->toBeNull()
            ->and(Slug::tryFromName("  {$reserved}  "))->toBeNull();
    })->with([
        'api', 'admin', 'dashboard', 'onboarding', 'calendar', 'services',
        'customers', 'settings', 'me', 'auth', 'login', 'register', 'logout',
        'sanctum', 'up', 'terms', 'privacy', 'cookies',
    ]);

    it('refuses a name that would collide with an admin url', function (string $name, string $collision) {
        expect(fn () => Slug::fromName($name))
            ->toThrow(BusinessNameNotSluggable::class, "The name [{$name}] does not produce a usable slug.")
            ->and(Slug::tryFromName($name))->toBeNull()
            ->and(fn () => Slug::fromString($collision))
            ->toThrow(InvalidBusinessSlug::class, "[{$collision}] is a reserved slug.");
    })->with([
        'calendar' => ['Calendar', 'calendar'],
        'customers' => ['Customers', 'customers'],
    ]);

    it('allows a name that merely contains a reserved word', function () {
        expect(Slug::fromName('Admin Barbers')->value)->toBe('admin-barbers')
            ->and(Slug::fromName('Calendar Barbers')->value)->toBe('calendar-barbers')
            ->and(Slug::fromName('My Customers Salon')->value)->toBe('my-customers-salon');
    });

    it('returns null from tryFromName in exactly the cases fromName throws', function (string $name) {
        $thrown = false;

        try {
            Slug::fromName($name);
        } catch (BusinessNameNotSluggable) {
            $thrown = true;
        }

        expect($thrown)->toBe(Slug::tryFromName($name) === null);
    })->with([
        'usable' => 'Barbería Ñandú',
        'empty' => '',
        'spaces' => '   ',
        'punctuation only' => '***',
        'reserved' => 'Admin',
        'reserved padded' => '  Login  ',
        'unsupported script' => '北京',
    ]);
});

describe('the sixty character limit', function () {
    it('keeps a name that fits untouched', function () {
        $slug = Slug::fromName(str_repeat('a', 60));

        expect($slug->value)->toBe(str_repeat('a', 60))
            ->and(strlen($slug->value))->toBe(60);
    });

    it('cuts at the last word boundary that fits, so the address still reads as words', function () {
        $slug = Slug::fromName('Alpha Beta Gamma Delta Epsilon Zeta Eta Theta Iota Kappa Lambda Mu');

        expect($slug->value)->toBe('alpha-beta-gamma-delta-epsilon-zeta-eta-theta-iota-kappa')
            ->and(strlen($slug->value))->toBe(56)
            ->and($slug->value)->not->toEndWith('-');
    });

    it('clips a single word with no boundary to cut at', function () {
        $slug = Slug::fromName('Supercalifragilisticexpialidociousandthenanotherverylongwordthatneverends');

        expect($slug->value)->toBe('supercalifragilisticexpialidociousandthenanotherverylongword')
            ->and(strlen($slug->value))->toBe(60);
    });

    it('measures the limit after transliteration, not before', function () {
        $slug = Slug::fromName(str_repeat('ß', 31));

        expect(strlen($slug->value))->toBe(60)
            ->and($slug->value)->toBe(str_repeat('ss', 30));
    });
});

describe('numbering a taken address', function () {
    it('appends the suffix', function () {
        expect(Slug::fromName('Barbería Ñandú')->withSuffix(2)->value)->toBe('barberia-nandu-2')
            ->and(Slug::fromName('Barbería Ñandú')->withSuffix(11)->value)->toBe('barberia-nandu-11');
    });

    it('leaves the original untouched, because a value object is not edited', function () {
        $base = Slug::fromName('Barberia Nandu');

        $base->withSuffix(2);

        expect($base->value)->toBe('barberia-nandu');
    });

    it('starts at two, since the first business is conceptually the one', function (int $suffix) {
        expect(fn () => Slug::fromName('Barberia Nandu')->withSuffix($suffix))
            ->toThrow(InvalidArgumentException::class, "A slug suffix starts at 2, got [{$suffix}].");
    })->with([
        'one' => 1,
        'zero' => 0,
        'negative' => -3,
    ]);

    it('shortens the base so the numbered address still fits', function () {
        $base = Slug::fromName('Barbería La Esquina de Don José Luis Martínez en el Centro Histórico');

        expect($base->value)->toBe('barberia-la-esquina-de-don-jose-luis-martinez-en-el-centro')
            ->and(strlen($base->value))->toBe(58);

        expect($base->withSuffix(2)->value)->toBe('barberia-la-esquina-de-don-jose-luis-martinez-en-el-centro-2')
            ->and(strlen($base->withSuffix(2)->value))->toBe(60);

        expect($base->withSuffix(10)->value)->toBe('barberia-la-esquina-de-don-jose-luis-martinez-en-el-centr-10')
            ->and(strlen($base->withSuffix(10)->value))->toBe(60);
    });

    it('never leaves a double hyphen when the cut lands on a boundary', function () {
        $base = Slug::fromName(str_repeat('a', 57).' bb');
        $numbered = $base->withSuffix(2);

        expect(strlen($base->value))->toBe(60)
            ->and($numbered->value)->toBe(str_repeat('a', 57).'-2')
            ->and($numbered->value)->not->toContain('--');
    });
});

describe('a slug arriving as a string', function () {
    it('accepts one this class could have produced', function (string $value) {
        expect(Slug::fromString($value)->value)->toBe($value);
    })->with([
        'a word' => 'barberia',
        'hyphenated' => 'barberia-nandu',
        'numbered' => 'barberia-nandu-2',
        'digits' => 'studio-54',
        'at the limit' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ]);

    it('refuses anything outside the shape', function (string $value) {
        expect(fn () => Slug::fromString($value))
            ->toThrow(InvalidBusinessSlug::class, "[{$value}] is not a valid business slug.");
    })->with([
        'empty' => '',
        'upper case' => 'Barberia',
        'a space' => 'barberia nandu',
        'an accent' => 'barbería',
        'leading hyphen' => '-barberia',
        'trailing hyphen' => 'barberia-',
        'double hyphen' => 'barberia--nandu',
        'a slash' => 'barberia/nandu',
        'a LIKE wildcard' => 'barberia%',
        'an underscore, which LIKE also treats as a wildcard' => 'barberia_nandu',
        'over the limit' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ]);

    it('refuses a reserved word with its own message', function () {
        expect(fn () => Slug::fromString('admin'))
            ->toThrow(InvalidBusinessSlug::class, '[admin] is a reserved slug.');
    });
});

describe('rehydrating from storage', function () {
    it('accepts a stored value that fromString would refuse', function (string $stored) {
        expect(Slug::restore($stored)->value)->toBe($stored);
    })->with([
        'written before the rule' => 'Barberia_Nandu',
        'reserved' => 'admin',
        'empty' => '',
        'over the limit' => str_repeat('a', 120),
    ]);
});

describe('equality', function () {
    it('compares by value, not by identity', function () {
        expect(Slug::fromName('Barbería Ñandú')->equals(Slug::fromName('barberia nandu')))->toBeTrue()
            ->and(Slug::fromName('Barberia')->equals(Slug::fromName('Peluqueria')))->toBeFalse()
            ->and(Slug::fromName('Barberia')->equals(Slug::fromName('Barberia')->withSuffix(2)))->toBeFalse();
    });
});
