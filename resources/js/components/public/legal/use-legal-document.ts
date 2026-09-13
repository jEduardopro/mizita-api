import { useEffect, useState } from 'react';
import { parseLegalContent, type LegalContent } from '@/components/public/legal/legal-content';
import type { LegalDocumentName } from '@/content/legal/entity';

/**
 * Loads one legal document, in the language being read, and hands back its
 * parsed contents.
 *
 * The six documents are some fifty kilobytes of prose that nobody reads on the
 * way to booking an appointment, so they are deliberately *not* bundled the way
 * the i18next catalogues are: `import.meta.glob` without `eager` leaves each
 * file in a chunk of its own, fetched the first time someone opens that page in
 * that language and never loaded at all for everyone else.
 */

/** The languages a document is actually written in. */
const CONTENT_LOCALES = ['es', 'en'] as const;

export type ContentLocale = (typeof CONTENT_LOCALES)[number];

/**
 * The language the documents were written in first, and the one a reader falls
 * back to. A missing translation has to show the Spanish text: a blank page is
 * the one outcome a legal notice may never have.
 */
const FALLBACK_LOCALE: ContentLocale = 'es';

const sources = import.meta.glob<string>('../../../content/legal/*/*.md', {
    query: '?raw',
    import: 'default',
});

function sourcePath(locale: ContentLocale, documentName: LegalDocumentName): string {
    return `../../../content/legal/${locale}/${documentName}.md`;
}

function isContentLocale(value: string): value is ContentLocale {
    return (CONTENT_LOCALES as readonly string[]).includes(value);
}

/**
 * The document language for a UI language. A regional tag resolves to its base
 * language, so `es-MX` reads the Spanish document rather than falling through to
 * it by accident.
 */
export function resolveContentLocale(language: string): ContentLocale {
    const base = language.split('-')[0].toLowerCase();

    return isContentLocale(base) ? base : FALLBACK_LOCALE;
}

export type LegalDocumentState =
    { status: 'loading' } | { status: 'failed' } | { status: 'ready'; content: LegalContent };

export function useLegalDocument(
    documentName: LegalDocumentName,
    language: string,
): LegalDocumentState {
    const locale = resolveContentLocale(language);
    const [state, setState] = useState<LegalDocumentState>({
        status: 'loading',
    });

    useEffect(() => {
        const load = sources[sourcePath(locale, documentName)];

        if (load === undefined) {
            setState({ status: 'failed' });

            return;
        }

        // A language switched mid-read starts a second load while the first is
        // still in flight, and the two can land in either order.
        let current = true;

        setState({ status: 'loading' });

        void load()
            .then((markdown) => {
                if (current) {
                    setState({
                        status: 'ready',
                        content: parseLegalContent(markdown),
                    });
                }
            })
            .catch(() => {
                if (current) {
                    setState({ status: 'failed' });
                }
            });

        return () => {
            current = false;
        };
    }, [documentName, locale]);

    return state;
}
