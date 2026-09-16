<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Eloquent\Factories\BusinessModelFactory;
use App\Domains\Industries\Infrastructure\Eloquent\Factories\IndustryModelFactory;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function businessDefinition(): array
{
    return BusinessModelFactory::new()->definition();
}

it('supplies every column a business row needs', function () {
    expect(businessDefinition())->toHaveKeys([
        'name',
        'slug',
        'industry_id',
        'timezone',
        'contact_email',
        'about',
        'currency_code',
    ]);
});

it('supplies the brand fields, so a fixture exercises them instead of leaving them null', function () {
    $definition = businessDefinition();

    expect($definition['contact_email'])->toBeString()->not->toBe('')
        ->and($definition['about'])->toBeString()->not->toBe('')
        ->and($definition['currency_code'])->toBe('MXN');
});

it('supplies a contact email the value object would accept', function () {
    expect(filter_var(businessDefinition()['contact_email'], FILTER_VALIDATE_EMAIL))->not->toBeFalse();
});

it('supplies a description within the limit the value object enforces', function () {
    expect(mb_strlen(businessDefinition()['about']))->toBeLessThanOrEqual(2000);
});

it('leaves the industry to its own factory rather than inventing a key', function () {
    expect(businessDefinition()['industry_id'])->toBeInstanceOf(IndustryModelFactory::class);
});

it('gives each business a distinct name and a slug matching it', function () {
    $first = businessDefinition();
    $second = businessDefinition();

    expect($first['slug'])->toBe(strtolower(str_replace(' ', '-', $first['name'])))
        ->and($first['name'])->not->toBe($second['name']);
});
