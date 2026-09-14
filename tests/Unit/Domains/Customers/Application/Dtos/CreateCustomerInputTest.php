<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CreateCustomerInput;

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
    // A browser posts an untouched optional field as "", and a blank column and
    // an absent one have to mean the same thing or every read has to check both.
    'the keys are empty strings' => [['email' => '', 'phone' => '']],
]);

it('carries the name untouched, because the entity is what rules on it', function () {
    // Trimming here would make the DTO the place a blank name stops being blank,
    // and the entity's not-empty invariant would never fire.
    expect(CreateCustomerInput::fromRequest(['name' => '   '])->name)->toBe('   ');
});

it('ignores a tenant the client tried to choose for itself', function () {
    // The business comes from BusinessContext, never from the body. A DTO that
    // read one here would be a cross-tenant write waiting to happen.
    $input = CreateCustomerInput::fromRequest([
        'name' => 'Ada Lovelace',
        'business_id' => 'another-business-uuid',
    ]);

    expect($input)->toEqual(CreateCustomerInput::fromRequest(['name' => 'Ada Lovelace']));
});
