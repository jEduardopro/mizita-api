<?php

declare(strict_types=1);

use App\Domains\Addresses\Entities\State;
use App\Domains\Addresses\Services\StateMatcher;
use App\Shared\ValueObjects\CountryCode;

function catalogState(string $id, CountryCode $country, string $code, string $name, int $position = 1): State
{
    return State::restore(
        id: $id,
        country: $country,
        code: $code,
        name: $name,
        position: $position,
        active: true,
    );
}

beforeEach(function () {
    $this->matcher = new StateMatcher;

    $this->nuevoLeon = catalogState('01930000-0000-7000-8000-000000000001', CountryCode::Mx, 'NLE', 'Nuevo León', 19);
    $this->queretaro = catalogState('01930000-0000-7000-8000-000000000002', CountryCode::Mx, 'QUE', 'Querétaro', 22);
    $this->ciudadDeMexico = catalogState('01930000-0000-7000-8000-000000000003', CountryCode::Mx, 'CMX', 'Ciudad de México', 9);
    $this->mexico = catalogState('01930000-0000-7000-8000-000000000004', CountryCode::Mx, 'MEX', 'México', 15);
    $this->texas = catalogState('01930000-0000-7000-8000-000000000005', CountryCode::Us, 'TX', 'Texas', 43);
    $this->california = catalogState('01930000-0000-7000-8000-000000000006', CountryCode::Us, 'CA', 'California', 5);

    $this->catalog = [
        $this->nuevoLeon,
        $this->queretaro,
        $this->ciudadDeMexico,
        $this->mexico,
        $this->texas,
        $this->california,
    ];
});

describe('when the same name or code exists in both countries', function () {
    beforeEach(function () {
        $this->mexicanFrontera = catalogState('01930000-0000-7000-8000-000000000011', CountryCode::Mx, 'FRO', 'Frontera');
        $this->americanFrontera = catalogState('01930000-0000-7000-8000-000000000012', CountryCode::Us, 'FR', 'Frontera');
        $this->mexicanColimaNorte = catalogState('01930000-0000-7000-8000-000000000013', CountryCode::Mx, 'CO', 'Colima Norte');
        $this->americanColorado = catalogState('01930000-0000-7000-8000-000000000014', CountryCode::Us, 'CO', 'Colorado');
    });

    it('returns the row of the preferred country for a shared name', function (CountryCode $preferredCountry, string $expectedId) {
        $states = [$this->mexicanFrontera, $this->americanFrontera];

        expect($this->matcher->bestMatch($states, $preferredCountry, 'Frontera')?->id)->toBe($expectedId);
    })->with([
        'preferring Mexico' => [CountryCode::Mx, '01930000-0000-7000-8000-000000000011'],
        'preferring the United States' => [CountryCode::Us, '01930000-0000-7000-8000-000000000012'],
    ]);

    it('returns the row of the preferred country for a shared code', function (CountryCode $preferredCountry, string $expectedId) {
        $states = [$this->mexicanColimaNorte, $this->americanColorado];

        expect($this->matcher->bestMatch($states, $preferredCountry, 'CO')?->id)->toBe($expectedId);
    })->with([
        'preferring Mexico' => [CountryCode::Mx, '01930000-0000-7000-8000-000000000013'],
        'preferring the United States' => [CountryCode::Us, '01930000-0000-7000-8000-000000000014'],
    ]);

    it('ranks by preference rather than by the order the rows arrived in', function (CountryCode $preferredCountry, string $expectedId) {
        $states = [$this->americanFrontera, $this->mexicanFrontera];

        expect($this->matcher->bestMatch($states, $preferredCountry, 'Frontera')?->id)->toBe($expectedId);
    })->with([
        'preferring Mexico' => [CountryCode::Mx, '01930000-0000-7000-8000-000000000011'],
        'preferring the United States' => [CountryCode::Us, '01930000-0000-7000-8000-000000000012'],
    ]);
});

describe('when only the other country has the state', function () {
    it('falls back to Texas while preferring Mexico', function () {
        expect($this->matcher->bestMatch($this->catalog, CountryCode::Mx, 'texas'))->toBe($this->texas);
    });

    it('falls back to México while preferring the United States', function () {
        expect($this->matcher->bestMatch($this->catalog, CountryCode::Us, 'mexico'))->toBe($this->mexico);
    });
});

describe('matching by code', function () {
    it('ignores the case of the typed code', function (string $typed, string $expectedCode) {
        expect($this->matcher->bestMatch($this->catalog, CountryCode::Mx, $typed)?->code())->toBe($expectedCode);
    })->with([
        'lowercase TX' => ['tx', 'TX'],
        'uppercase NLE' => ['NLE', 'NLE'],
        'lowercase nle' => ['nle', 'NLE'],
        'mixed case Cmx' => ['Cmx', 'CMX'],
    ]);
});

describe('normalising the typed text', function () {
    it('matches the stored name regardless of accents, case and whitespace', function (string $typed, string $expectedName) {
        expect($this->matcher->bestMatch($this->catalog, CountryCode::Mx, $typed)?->name())->toBe($expectedName);
    })->with([
        'padded, doubled spaces and shouting' => ['  nuevo   LEON ', 'Nuevo León'],
        'accent left off' => ['Nuevo Leon', 'Nuevo León'],
        'accent typed' => ['Nuevo León', 'Nuevo León'],
        'lowercase without accent' => ['queretaro', 'Querétaro'],
        'several words without accent' => ['ciudad de mexico', 'Ciudad de México'],
        'tab between words' => ["nuevo\tleón", 'Nuevo León'],
        'tabs and newlines around' => ["\t\nNuevo León\r\n", 'Nuevo León'],
        'non-breaking space between words' => ["Nuevo\u{00A0}León", 'Nuevo León'],
        'run of mixed whitespace between words' => ["Ciudad \u{00A0}\tde\u{2003}México", 'Ciudad de México'],
        'uppercase accented letter' => ['QUERÉTARO', 'Querétaro'],
    ]);

    it('matches a stored name that itself carries irregular whitespace', function () {
        $sloppyRow = catalogState('01930000-0000-7000-8000-000000000021', CountryCode::Mx, 'SLP', "  San  Luis\tPotosí ");

        expect($this->matcher->bestMatch([$sloppyRow], CountryCode::Mx, 'san luis potosi'))->toBe($sloppyRow);
    });

    it('treats a non-breaking space around the text like any other whitespace', function () {
        expect($this->matcher->bestMatch($this->catalog, CountryCode::Mx, "\u{00A0}Nuevo León\u{00A0}"))->toBe($this->nuevoLeon);
    });
});

describe('within one country', function () {
    beforeEach(function () {
        $this->firstRow = catalogState('01930000-0000-7000-8000-000000000031', CountryCode::Mx, 'DUR', 'Durango', 10);
        $this->secondRow = catalogState('01930000-0000-7000-8000-000000000032', CountryCode::Mx, 'DGO', 'Durango', 1);
    });

    it('keeps the input order when two rows match', function () {
        expect($this->matcher->bestMatch([$this->firstRow, $this->secondRow], CountryCode::Mx, 'durango'))
            ->toBe($this->firstRow);
    });

    it('follows the input order when it is reversed', function () {
        expect($this->matcher->bestMatch([$this->secondRow, $this->firstRow], CountryCode::Mx, 'durango'))
            ->toBe($this->secondRow);
    });

    it('keeps the input order within the fallback country too', function () {
        expect($this->matcher->bestMatch([$this->texas, $this->firstRow, $this->secondRow], CountryCode::Us, 'durango'))
            ->toBe($this->firstRow);
    });

    it('does not reorder by position', function () {
        expect($this->firstRow->position())->toBeGreaterThan($this->secondRow->position())
            ->and($this->matcher->bestMatch([$this->firstRow, $this->secondRow], CountryCode::Mx, 'durango'))
            ->toBe($this->firstRow);
    });
});

describe('when nothing matches', function () {
    it('finds no state for a name no row carries', function () {
        expect($this->matcher->bestMatch($this->catalog, CountryCode::Mx, 'Ontario'))->toBeNull();
    });

    it('finds no state in an empty list', function (CountryCode $preferredCountry) {
        expect($this->matcher->bestMatch([], $preferredCountry, 'Texas'))->toBeNull();
    })->with([
        'preferring Mexico' => CountryCode::Mx,
        'preferring the United States' => CountryCode::Us,
    ]);

    it('does not match on partial text', function (string $typed) {
        expect($this->matcher->bestMatch($this->catalog, CountryCode::Mx, $typed))->toBeNull();
    })->with([
        'first word of the name' => 'nuevo',
        'last word of the name' => 'león',
        'name prefix' => 'Tex',
        'code prefix' => 'NL',
        'name with an extra word' => 'Nuevo León Norte',
    ]);
});

describe('blank text', function () {
    it('cannot be matched', function (string $blank) {
        expect($this->matcher->canMatch($blank))->toBeFalse();
    })->with('blank state text');

    it('finds no state', function (string $blank) {
        expect($this->matcher->bestMatch($this->catalog, CountryCode::Mx, $blank))->toBeNull();
    })->with('blank state text');

    it('finds no state even when a row has a blank code', function (string $blank) {
        $codelessRow = catalogState('01930000-0000-7000-8000-000000000041', CountryCode::Mx, '', 'Sin Código');

        expect($this->matcher->bestMatch([$codelessRow], CountryCode::Mx, $blank))->toBeNull();
    })->with('blank state text');
});

it('can match text that carries a name or a code', function (string $text) {
    expect((new StateMatcher)->canMatch($text))->toBeTrue();
})->with([
    'name' => 'Texas',
    'code' => 'TX',
    'padded name' => '  Nuevo León  ',
    'single letter' => 'a',
]);

dataset('blank state text', [
    'empty' => '',
    'spaces' => '   ',
    'tab' => "\t",
    'tabs and newlines' => "\t\n\r ",
    'non-breaking space' => "\u{00A0}",
    'em space and non-breaking space' => "\u{2003}\u{00A0} ",
]);
