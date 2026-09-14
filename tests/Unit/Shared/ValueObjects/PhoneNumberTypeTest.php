<?php

declare(strict_types=1);

use App\Shared\Infrastructure\LibPhoneNumberParser;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumberType;
use libphonenumber\PhoneNumberType as LibPhoneNumberType;

function phoneNumberTypeToken(string $caseName): string
{
    return strtolower(str_replace('_', '', $caseName));
}

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
