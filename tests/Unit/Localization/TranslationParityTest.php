<?php

declare(strict_types=1);

/*
| Keeps every locale's catalogues in step. A missing key does not fail loudly at
| runtime - Laravel silently echoes the key itself back to the user - so the only
| place it can be caught is here, as the files grow.
|
| Two kinds of catalogue are covered, because the framework resolves keys against
| both. A dotted key like validation.required is split by Translator::parseKey()
| and looked up in lang/<locale>/validation.php; a key with no dot, such as the
| "(and :count more errors)" sentence ValidationException::summarize() builds its
| message from, is only ever found in lang/<locale>.json. Walking the PHP files
| alone would leave the JSON catalogues unguarded.
|
| Pure PHP on purpose: the catalogues are plain arrays, so this needs no
| container, no translator and no database.
*/

use PHPUnit\Framework\Assert;

/**
 * Flattens a catalogue into a dot-notated key => value map.
 *
 * JSON catalogues are flat and their keys are whole sentences that may contain a
 * dot themselves ("The given data was invalid."). Returning the map rather than
 * re-resolving each key by splitting on "." later is what keeps those intact.
 *
 * @param  array<array-key, mixed>  $translations
 * @return array<string, mixed>
 */
function mizitaFlattenTranslations(array $translations, string $prefix = ''): array
{
    $flat = [];

    foreach ($translations as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value) && $value !== []) {
            $flat = [...$flat, ...mizitaFlattenTranslations($value, $path)];

            continue;
        }

        $flat[$path] = $value;
    }

    return $flat;
}

/**
 * The catalogues a locale ships, identified by a name that does not vary with the
 * locale: "validation.php" for lang/<locale>/validation.php, and "json" for the
 * lang/<locale>.json catalogue, whose real filename is the locale itself.
 *
 * @return list<string>
 */
function mizitaCatalogueIds(string $locale): array
{
    $ids = array_map(basename(...), glob(mizitaLangDir().'/'.$locale.'/*.php') ?: []);

    if (is_file(mizitaLangDir().'/'.$locale.'.json')) {
        $ids[] = 'json';
    }

    sort($ids);

    return array_values($ids);
}

function mizitaCataloguePath(string $locale, string $id): string
{
    return $id === 'json'
        ? mizitaLangDir().'/'.$locale.'.json'
        : mizitaLangDir().'/'.$locale.'/'.$id;
}

/**
 * @return array<array-key, mixed>
 */
function mizitaLoadCatalogue(string $locale, string $id): array
{
    $path = mizitaCataloguePath($locale, $id);

    if ($id !== 'json') {
        return require $path;
    }

    $decoded = json_decode((string) file_get_contents($path), associative: true);

    Assert::assertIsArray($decoded, sprintf('%s is not valid JSON.', mizitaRelativePath($path)));

    return $decoded;
}

function mizitaLangDir(): string
{
    return dirname(__DIR__, 3).'/lang';
}

function mizitaRelativePath(string $path): string
{
    return 'lang'.substr($path, strlen(mizitaLangDir()));
}

it('ships the same catalogues in every supported locale', function () {
    $en = mizitaCatalogueIds('en');
    $es = mizitaCatalogueIds('es');

    expect($en)->not->toBeEmpty()
        ->and($en)->toContain('json')
        ->and($es)->toBe($en);
});

it('defines every english key in spanish and every spanish key in english', function (string $catalogue) {
    $enKeys = array_keys(mizitaFlattenTranslations(mizitaLoadCatalogue('en', $catalogue)));
    $esKeys = array_keys(mizitaFlattenTranslations(mizitaLoadCatalogue('es', $catalogue)));

    $missingInEs = array_values(array_diff($enKeys, $esKeys));
    $missingInEn = array_values(array_diff($esKeys, $enKeys));

    Assert::assertSame([], $missingInEs, sprintf(
        '%s is missing keys present in %s: %s',
        mizitaRelativePath(mizitaCataloguePath('es', $catalogue)),
        mizitaRelativePath(mizitaCataloguePath('en', $catalogue)),
        implode(', ', $missingInEs),
    ));

    Assert::assertSame([], $missingInEn, sprintf(
        '%s is missing keys present in %s: %s',
        mizitaRelativePath(mizitaCataloguePath('en', $catalogue)),
        mizitaRelativePath(mizitaCataloguePath('es', $catalogue)),
        implode(', ', $missingInEn),
    ));
})->with(mizitaCatalogueIds('en'));

it('leaves no translation string empty', function (string $catalogue) {
    foreach (['en', 'es'] as $locale) {
        foreach (mizitaFlattenTranslations(mizitaLoadCatalogue($locale, $catalogue)) as $key => $value) {
            Assert::assertNotSame('', $value, sprintf(
                '%s has an empty value at "%s".',
                mizitaRelativePath(mizitaCataloguePath($locale, $catalogue)),
                $key,
            ));
        }
    }
})->with(mizitaCatalogueIds('en'));
