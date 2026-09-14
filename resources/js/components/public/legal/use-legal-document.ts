import { useEffect, useState } from 'react';
import { parseLegalContent, type LegalContent } from '@/components/public/legal/legal-content';
import type { LegalDocumentName } from '@/content/legal/entity';

// Fifty kilobytes of prose nobody reads on the way to booking, so unlike the
// i18next catalogues these are not bundled: `import.meta.glob` without `eager`
// leaves each file in a chunk of its own.

/** The languages a document is actually written in. */
const CONTENT_LOCALES = ['es', 'en'] as const;

export type ContentLocale = (typeof CONTENT_LOCALES)[number];

/** A missing translation shows the Spanish text: a legal notice is never blank. */
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

/** A regional tag resolves to its base language, so `es-MX` reads Spanish. */
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
