<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Purge\DatabaseTenantDataEraser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function schemaTables(): array
{
    $tables = DB::table('information_schema.tables')
        ->where('table_schema', 'public')
        ->where('table_type', 'BASE TABLE')
        ->pluck('table_name')
        ->all();

    sort($tables);

    return $tables;
}

/**
 * @return list<string>
 */
function schemaTablesWithBusinessColumn(): array
{
    $tables = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('column_name', 'business_id')
        ->distinct()
        ->pluck('table_name')
        ->all();

    sort($tables);

    return $tables;
}

/**
 * @return list<string>
 */
function classifiedTables(): array
{
    return [
        ...DatabaseTenantDataEraser::ERASED_TABLES,
        ...DatabaseTenantDataEraser::FILE_TABLES,
        ...DatabaseTenantDataEraser::PRESERVED_TABLES,
    ];
}

it('reads a non empty schema, so the classification never passes by vacuity', function () {
    expect(schemaTables())->toContain('businesses', 'users');
});

it('classifies every table of the schema', function () {
    expect(array_values(array_diff(schemaTables(), classifiedTables())))->toBe([]);
});

it('classifies no table the schema does not have', function () {
    expect(array_values(array_diff(classifiedTables(), schemaTables())))->toBe([]);
});

it('classifies every table exactly once', function () {
    $classified = classifiedTables();

    expect(array_values(array_diff_assoc($classified, array_unique($classified))))->toBe([]);
});

it('erases every table that carries a business column', function () {
    expect(array_values(array_diff(schemaTablesWithBusinessColumn(), DatabaseTenantDataEraser::coveredTables())))
        ->toBe([]);
});
