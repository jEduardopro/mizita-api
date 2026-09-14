export type LegalSection = {
    id: string;
    ordinal: string | null;
    label: string;
};

export type LegalContent = {
    title: string;
    body: string;
    sections: LegalSection[];
};

const TITLE_LINE = /^#\s+(.*)$/;
const SECTION_LINE = /^##\s+(.*)$/;

const NUMBERED_HEADING = /^(\d+(?:\.\d+)*)\.\s+(.*)$/;

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
