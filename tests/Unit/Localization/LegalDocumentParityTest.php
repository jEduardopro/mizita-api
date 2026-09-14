<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

/**
 * @return list<string>
 */
function mizitaLegalDocumentIds(string $locale): array
{
    $ids = array_map(
        static fn (string $path): string => basename($path, '.md'),
        glob(mizitaLegalDir().'/'.$locale.'/*.md') ?: [],
    );

    sort($ids);

    return array_values($ids);
}

function mizitaLegalDocumentPath(string $locale, string $document): string
{
    return mizitaLegalDir().'/'.$locale.'/'.$document.'.md';
}

function mizitaLoadLegalDocument(string $locale, string $document): string
{
    $path = mizitaLegalDocumentPath($locale, $document);
    $markdown = file_get_contents($path);

    Assert::assertIsString($markdown, sprintf('%s could not be read.', mizitaLegalRelativePath($path)));

    return $markdown;
}

/**
 * @return list<string>
 */
function mizitaLegalSections(string $markdown): array
{
    preg_match_all('/^##[ \t]+(.*)$/m', $markdown, $matches);

    return array_values($matches[1]);
}

/**
 * @return list<string>
 */
function mizitaLegalTokens(string $markdown): array
{
    preg_match_all('/\{\{([A-Za-z0-9_]+)\}\}/', $markdown, $matches);

    $tokens = array_values($matches[1]);
    sort($tokens);

    return $tokens;
}

/**
 * @return list<string>
 */
function mizitaDeclaredLegalFacts(): array
{
    $entity = (string) file_get_contents(mizitaLegalEntityPath());

    $matched = preg_match('/export const legalFacts = \{(.*?)^\} as const/ms', $entity, $block);

    Assert::assertSame(1, $matched, sprintf(
        '%s no longer declares legalFacts as a plain object literal, so this test can no longer read it.',
        mizitaLegalRelativePath(mizitaLegalEntityPath()),
    ));

    preg_match_all("/^\s+([A-Za-z0-9_]+):\s*'/m", $block[1], $matches);

    $names = array_values(array_unique($matches[1]));
    sort($names);

    return $names;
}

function mizitaLegalDir(): string
{
    return dirname(__DIR__, 3).'/resources/js/content/legal';
}

function mizitaLegalEntityPath(): string
{
    return mizitaLegalDir().'/entity.ts';
}

function mizitaLegalRelativePath(string $path): string
{
    return 'resources/js/content/legal'.substr($path, strlen(mizitaLegalDir()));
}

it('ships the same legal documents in every supported locale', function () {
    $en = mizitaLegalDocumentIds('en');
    $es = mizitaLegalDocumentIds('es');

    expect($en)->not->toBeEmpty()
        ->and($es)->toBe($en);
});

it('declares every interpolated fact in entity.ts', function () {
    $declared = mizitaDeclaredLegalFacts();

    foreach (['en', 'es'] as $locale) {
        foreach (mizitaLegalDocumentIds($locale) as $document) {
            $undeclared = array_values(array_unique(array_diff(
                mizitaLegalTokens(mizitaLoadLegalDocument($locale, $document)),
                $declared,
            )));

            Assert::assertSame([], $undeclared, sprintf(
                '%s interpolates facts that %s does not declare: %s',
                mizitaLegalRelativePath(mizitaLegalDocumentPath($locale, $document)),
                mizitaLegalRelativePath(mizitaLegalEntityPath()),
                implode(', ', $undeclared),
            ));
        }
    }
});

it('writes the same number of sections in both locales', function (string $document) {
    $en = mizitaLegalSections(mizitaLoadLegalDocument('en', $document));
    $es = mizitaLegalSections(mizitaLoadLegalDocument('es', $document));

    Assert::assertSame(count($en), count($es), sprintf(
        '%s has %d sections and %s has %d.',
        mizitaLegalRelativePath(mizitaLegalDocumentPath('en', $document)),
        count($en),
        mizitaLegalRelativePath(mizitaLegalDocumentPath('es', $document)),
        count($es),
    ));
})->with(mizitaLegalDocumentIds('en'));

it('interpolates the same facts the same number of times in both locales', function (string $document) {
    $en = mizitaLegalTokens(mizitaLoadLegalDocument('en', $document));
    $es = mizitaLegalTokens(mizitaLoadLegalDocument('es', $document));

    Assert::assertSame($en, $es, sprintf(
        '%s and %s interpolate different facts.',
        mizitaLegalRelativePath(mizitaLegalDocumentPath('es', $document)),
        mizitaLegalRelativePath(mizitaLegalDocumentPath('en', $document)),
    ));
})->with(mizitaLegalDocumentIds('en'));

it('gives every document content under exactly one title', function (string $document) {
    foreach (['en', 'es'] as $locale) {
        $markdown = mizitaLoadLegalDocument($locale, $document);
        $path = mizitaLegalRelativePath(mizitaLegalDocumentPath($locale, $document));

        Assert::assertNotSame('', trim($markdown), sprintf('%s is empty.', $path));

        Assert::assertSame(1, preg_match_all('/^#[ \t]+/m', $markdown), sprintf(
            '%s must have exactly one "# " title.',
            $path,
        ));
    }
})->with(mizitaLegalDocumentIds('en'));
