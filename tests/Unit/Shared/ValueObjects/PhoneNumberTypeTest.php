<?php

declare(strict_types=1);

use App\Shared\Infrastructure\LibPhoneNumberParser;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumberType;
use libphonenumber\PhoneNumberType as LibPhoneNumberType;

/*
| The platform keeps its own vocabulary for a fact it does not compute itself,
| and the adapter translates the library's enum into it with a total match. That
| match is only total for as long as the two enums line up, and nothing about a
| composer update says otherwise: a library that adds a type raises an
| UnhandledMatchError on the first parse after deploy, in a request, on a form
| that used to work.
|
| So the correspondence is asserted here, against the library's own cases(),
| which is where a version bump should go red instead.
|
| The correspondence is expressed between the two enums' case *names*, never
| between ours and the library's spelling of them. The coupling the tripwire
| guards is the one the adapter's match really has - library case to our case -
| and that is a different question from how either enum happens to spell a
| backed value. Tying the two together is what made a deliberate rename of our
| own storage vocabulary fail fifteen tests about libphonenumber's release
| notes. How our values are spelled is asserted on its own below.
|
| The three behavioural cases at the end exercise the translation end to end for
| the types real MX and US numbers can have. The other twelve cannot be produced
| by parsing anything the platform accepts, and the correspondence test is what
| covers them.
*/

/**
 * A case name reduced to the letters it is made of, so PascalCase and
 * SCREAMING_SNAKE_CASE spellings of the same type compare equal.
 */
function phoneNumberTypeToken(string $caseName): string
{
    return strtolower(str_replace('_', '', $caseName));
}

/** Our case naming the same type as the library's, or null when we name none. */
function ourPhoneNumberTypeFor(LibPhoneNumberType $libraryType): ?PhoneNumberType
{
    foreach (PhoneNumberType::cases() as $type) {
        if (phoneNumberTypeToken($type->name) === phoneNumberTypeToken($libraryType->name)) {
            return $type;
        }
    }

    return null;
}

it('has exactly one case per type the library distinguishes', function () {
    expect(PhoneNumberType::cases())->toHaveCount(count(LibPhoneNumberType::cases()));
});

it('names every type the library distinguishes', function (LibPhoneNumberType $libraryType) {
    expect(ourPhoneNumberTypeFor($libraryType))->toBeInstanceOf(PhoneNumberType::class);
})->with(fn () => array_map(
    static fn (LibPhoneNumberType $type): array => [$type],
    LibPhoneNumberType::cases(),
));

it('maps no two library types onto the same case', function () {
    $mapped = array_map(
        static fn (LibPhoneNumberType $type): ?PhoneNumberType => ourPhoneNumberTypeFor($type),
        LibPhoneNumberType::cases(),
    );

    expect(array_unique($mapped, SORT_REGULAR))->toHaveCount(count($mapped));
});

it('invents no type the library does not publish', function () {
    // The reverse direction: a case with no counterpart would be a value the
    // adapter can never produce, and a column value nothing would ever write.
    $libraryTokens = array_map(
        static fn (LibPhoneNumberType $type): string => phoneNumberTypeToken($type->name),
        LibPhoneNumberType::cases(),
    );

    $orphans = array_values(array_filter(
        PhoneNumberType::cases(),
        static fn (PhoneNumberType $type): bool => ! in_array(
            phoneNumberTypeToken($type->name),
            $libraryTokens,
            true,
        ),
    ));

    expect($orphans)->toBe([]);
});

it('backs every case in lowercase snake_case, like every other enum the project owns', function (PhoneNumberType $type) {
    // PhoneOwnerType writes staff_member and StaffRole writes owner, and the
    // value is what a translation key for the type will be built from, where
    // every key in this repository is lowercase snake_case. A case added with
    // the parsing library's shouted spelling would be the one exception, and
    // that deviation is what this asserts against - not the current values,
    // which are derived from the case name rather than restated.
    $expected = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $type->name));

    expect($type->value)->toBe($expected);
})->with(fn () => array_map(
    static fn (PhoneNumberType $type): array => [$type],
    PhoneNumberType::cases(),
));

it('translates a real number of each type the platform can actually see', function (
    CountryCode $country,
    string $nationalNumber,
    PhoneNumberType $expected,
) {
    expect((new LibPhoneNumberParser)->parse($country, $nationalNumber)->type())->toBe($expected);
})->with([
    'a range both a landline and a mobile may sit in' => [
        CountryCode::Mx, '5512345678', PhoneNumberType::FixedLineOrMobile,
    ],
    'a toll-free number' => [CountryCode::Us, '8002530000', PhoneNumberType::TollFree],
    'a United States geographic number' => [
        CountryCode::Us, '4155550100', PhoneNumberType::FixedLineOrMobile,
    ],
]);
