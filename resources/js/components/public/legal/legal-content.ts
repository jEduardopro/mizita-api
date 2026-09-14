// The documents are the source of truth for their own title and clause list, so
// neither is restated in a translation file.

export type LegalSection = {
    /** The slug the heading carries as its `id`, and the index links to. */
    id: string;
    /** The clause number the document gives itself, when it numbers its clauses. */
    ordinal: string | null;
    /** The heading without that number — the index sets the two in their own columns. */
    label: string;
};

export type LegalContent = {
    title: string;
    /** The document with that title removed, so the heading is never set twice. */
    body: string;
    sections: LegalSection[];
};

const TITLE_LINE = /^#\s+(.*)$/;
const SECTION_LINE = /^##\s+(.*)$/;

/** `1.`, `12.` or `3.1.` at the head of a heading, and the text after it. */
const NUMBERED_HEADING = /^(\d+(?:\.\d+)*)\.\s+(.*)$/;

/** Decomposing first is what makes `Términos` and `Terminos` the same slug. */
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
