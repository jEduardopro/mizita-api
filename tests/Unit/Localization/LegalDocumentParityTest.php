<?php

declare(strict_types=1);

/*
| The same guard FrontendTranslationParityTest gives the i18next bundles,
| applied to the legal documents in resources/js/content/legal.
|
| It is needed for a heavier reason. A JSON bundle that drifts renders a key
| instead of a sentence; a legal document that drifts states one thing to a
| Spanish reader and another to an English one. A clause dropped from one
| translation, or a {{TOKEN}} that names the operator in Spanish and nowhere in
| English, is a defect in the contract itself - and the only runtime that would
| notice is a person reading it, or a court.
|
| These files were in fact edited out of sync while they were being written,
| which is why the parity is asserted here rather than trusted to review.
|
| Pure PHP over markdown files: no container, no node, no build step.
*/

use PHPUnit\Framework\Assert;

/**
 * The documents a locale ships, by name - "cookies", "privacy", "terms".
 *
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
 * The "## " headings a document carries.
 *
 * Mirrors SECTION_LINE in components/public/legal/legal-content.ts, which is
 * what builds the table of contents: two hashes followed by whitespace, so a
 * deeper "### " heading is not a section.
 *
 * @return list<string>
 */
function mizitaLegalSections(string $markdown): array
{
    preg_match_all('/^##[ \t]+(.*)$/m', $markdown, $matches);

    return array_values($matches[1]);
}

/**
 * Every {{TOKEN}} occurrence in a document, in order and with repeats kept.
 *
 * The multiset matters, not the set: a fact stated three times in Spanish and
 * twice in English is still a document that says less in one language.
 *
 * Mirrors the TOKEN pattern in components/public/legal/rehype-legal-facts.ts.
 *
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
 * The keys declared in `legalFacts` in entity.ts.
 *
 * Read with a regex rather than executed: the suite has no TypeScript runtime,
 * and the declaration is a flat object literal whose keys are the whole
 * contract. The block is bounded so the sibling `legalDocuments` and
 * `legalUpdatedOn` objects cannot be mistaken for facts.
 *
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
    // An undeclared token does not fail, LegalDocument renders it as an
    // "unknown" marker in the middle of the prose. The suite should be what
    // notices that, not a reader.
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
    // The table of contents and the clause numbering are both derived from the
    // "## " headings, so a clause missing from one translation is a document
    // that grants or reserves something in only one language.
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
