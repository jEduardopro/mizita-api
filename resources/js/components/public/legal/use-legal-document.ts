import { useEffect, useState } from 'react';
import { parseLegalContent, type LegalContent } from '@/components/public/legal/legal-content';
import type { LegalDocumentName } from '@/content/legal/entity';

const CONTENT_LOCALES = ['es', 'en'] as const;

export type ContentLocale = (typeof CONTENT_LOCALES)[number];

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
