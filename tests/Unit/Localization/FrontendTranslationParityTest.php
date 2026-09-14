<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

/**
 * @param  array<array-key, mixed>  $translations
 * @return array<string, mixed>
 */
function mizitaFlattenFrontendBundle(array $translations, string $prefix = ''): array
{
    $flat = [];

    foreach ($translations as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value) && $value !== []) {
            $flat = [...$flat, ...mizitaFlattenFrontendBundle($value, $path)];

            continue;
        }

        $flat[$path] = $value;
    }

    return $flat;
}

/**
 * @return list<string>
 */
function mizitaFrontendBundleIds(string $locale): array
{
    $ids = array_map(basename(...), glob(mizitaFrontendLocalesDir().'/'.$locale.'/*.json') ?: []);

    sort($ids);

    return array_values($ids);
}

function mizitaFrontendBundlePath(string $locale, string $bundle): string
{
    return mizitaFrontendLocalesDir().'/'.$locale.'/'.$bundle;
}

/**
 * @return array<array-key, mixed>
 */
function mizitaLoadFrontendBundle(string $locale, string $bundle): array
{
    $path = mizitaFrontendBundlePath($locale, $bundle);
    $decoded = json_decode((string) file_get_contents($path), associative: true);

    Assert::assertIsArray($decoded, sprintf('%s is not valid JSON.', mizitaFrontendRelativePath($path)));

    return $decoded;
}

/**
 * @return list<string>
 */
function mizitaInterpolations(string $translation): array
{
    preg_match_all('/{{\s*([^}\s]+)\s*}}/', $translation, $matches);

    $names = array_values(array_unique($matches[1]));
    sort($names);

    return $names;
}

function mizitaFrontendLocalesDir(): string
{
    return dirname(__DIR__, 3).'/resources/js/locales';
}

function mizitaFrontendRelativePath(string $path): string
{
    return 'resources/js/locales'.substr($path, strlen(mizitaFrontendLocalesDir()));
}

it('ships the same bundles in every supported locale', function () {
    $en = mizitaFrontendBundleIds('en');
    $es = mizitaFrontendBundleIds('es');

    expect($en)->not->toBeEmpty()
        ->and($es)->toBe($en);
});

it('defines every english key in spanish and every spanish key in english', function (string $bundle) {
    $enKeys = array_keys(mizitaFlattenFrontendBundle(mizitaLoadFrontendBundle('en', $bundle)));
    $esKeys = array_keys(mizitaFlattenFrontendBundle(mizitaLoadFrontendBundle('es', $bundle)));

    $missingInEs = array_values(array_diff($enKeys, $esKeys));
    $missingInEn = array_values(array_diff($esKeys, $enKeys));

    Assert::assertSame([], $missingInEs, sprintf(
        '%s is missing keys present in %s: %s',
        mizitaFrontendRelativePath(mizitaFrontendBundlePath('es', $bundle)),
        mizitaFrontendRelativePath(mizitaFrontendBundlePath('en', $bundle)),
        implode(', ', $missingInEs),
    ));

    Assert::assertSame([], $missingInEn, sprintf(
        '%s is missing keys present in %s: %s',
        mizitaFrontendRelativePath(mizitaFrontendBundlePath('en', $bundle)),
        mizitaFrontendRelativePath(mizitaFrontendBundlePath('es', $bundle)),
        implode(', ', $missingInEn),
    ));
})->with(mizitaFrontendBundleIds('en'));

it('leaves no translation string empty', function (string $bundle) {
    foreach (['en', 'es'] as $locale) {
        foreach (mizitaFlattenFrontendBundle(mizitaLoadFrontendBundle($locale, $bundle)) as $key => $value) {
            Assert::assertNotSame('', $value, sprintf(
                '%s has an empty value at "%s".',
                mizitaFrontendRelativePath(mizitaFrontendBundlePath($locale, $bundle)),
                $key,
            ));
        }
    }
})->with(mizitaFrontendBundleIds('en'));

it('interpolates the same placeholders in both locales', function (string $bundle) {
    $en = mizitaFlattenFrontendBundle(mizitaLoadFrontendBundle('en', $bundle));
    $es = mizitaFlattenFrontendBundle(mizitaLoadFrontendBundle('es', $bundle));

    foreach ($en as $key => $value) {
        if (! is_string($value) || ! is_string($es[$key] ?? null)) {
            continue;
        }

        Assert::assertSame(mizitaInterpolations($value), mizitaInterpolations($es[$key]), sprintf(
            '%s interpolates different variables than %s at "%s".',
            mizitaFrontendRelativePath(mizitaFrontendBundlePath('es', $bundle)),
            mizitaFrontendRelativePath(mizitaFrontendBundlePath('en', $bundle)),
            $key,
        ));
    }
})->with(mizitaFrontendBundleIds('en'));
