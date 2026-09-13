/**
 * Reading a legal document's own structure out of its markdown.
 *
 * The documents are the source of truth for their title and their clause list,
 * so neither is restated in a translation file: a clause renamed in the markdown
 * renames itself in the index, and a clause added appears there without anyone
 * remembering to add it. The parse happens once, in `LegalDocument`, and the
 * result is what the index and the body are both built from.
 */

/** One `## ` clause: what the index lists and what an anchor points at. */
export type LegalSection = {
    /** The slug the heading carries as its `id`, and the index links to. */
    id: string;
    /** The clause number the document gives itself, when it numbers its clauses. */
    ordinal: string | null;
    /** The heading without that number — the index sets the two in their own columns. */
    label: string;
};

export type LegalContent = {
    /** The `# ` title, which the page prints as its own heading. */
    title: string;
    /** The document with that title removed, so the heading is never set twice. */
    body: string;
    sections: LegalSection[];
};

const TITLE_LINE = /^#\s+(.*)$/;
const SECTION_LINE = /^##\s+(.*)$/;

/** `1.`, `12.` or `3.1.` at the head of a heading, and the text after it. */
const NUMBERED_HEADING = /^(\d+(?:\.\d+)*)\.\s+(.*)$/;

/**
 * The anchor a heading answers to.
 *
 * Decomposing first and dropping the combining marks is what makes `Términos`
 * and `Terminos` the same slug: the accent becomes its own character, and the
 * character class removes it without a table of substitutions to keep in step
 * with Spanish.
 */
export function slugify(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function toSection(heading: string): LegalSection {
    const numbered = NUMBERED_HEADING.exec(heading);

    return {
        id: slugify(heading),
        ordinal: numbered === null ? null : numbered[1],
        label: numbered === null ? heading : numbered[2],
    };
}

/**
 * Splits a document into the three things the page renders separately: its
 * title, its clause index, and the prose itself.
 */
export function parseLegalContent(markdown: string): LegalContent {
    const sections: LegalSection[] = [];
    const bodyLines: string[] = [];
    let title = '';

    for (const line of markdown.split('\n')) {
        const section = SECTION_LINE.exec(line);

        if (section !== null) {
            sections.push(toSection(section[1].trim()));
            bodyLines.push(line);

            continue;
        }

        const heading = TITLE_LINE.exec(line);

        if (heading !== null && title === '') {
            title = heading[1].trim();

            continue;
        }

        bodyLines.push(line);
    }

    return { title, body: bodyLines.join('\n').trim(), sections };
}
