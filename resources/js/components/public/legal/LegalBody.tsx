import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { LegalMarkdown } from '@/components/public/legal/LegalMarkdown';
import { LegalToc } from '@/components/public/legal/LegalToc';
import type { LegalDocumentState } from '@/components/public/legal/use-legal-document';

/**
 * The bars stand in for the shape that is about to arrive — an index, a clause
 * heading, a few lines of prose — rather than for a spinner, so the page does
 * not change silhouette when the document lands. The widths are irregular on
 * purpose: an even stack of identical bars reads as a loading graphic, and a
 * ragged one reads as text.
 */
const PLACEHOLDER_LINES = ['w-full', 'w-[97%]', 'w-[88%]', 'w-[94%]', 'w-[62%]'];

function LegalBodySkeleton() {
    return (
        <div aria-hidden="true" className="min-h-[50svh] animate-pulse motion-reduce:animate-none">
            <div className="h-12 rounded-xl bg-muted lg:h-8 lg:w-40" />

            <div className="mt-10 h-6 w-2/5 rounded-md bg-muted" />

            <div className="mt-6 space-y-3">
                {PLACEHOLDER_LINES.map((width) => (
                    <div key={width} className={cn('h-3.5 rounded-sm bg-muted', width)} />
                ))}
            </div>
        </div>
    );
}

type Props = {
    state: LegalDocumentState;
};

/**
 * Everything under the document's masthead: the clause index, the prose, and
 * the two states that are neither.
 *
 * The index and the text arrive together rather than in sequence, so nothing a
 * reader is already looking at moves once the document lands — the page only
 * grows downwards, past the fold.
 */
export function LegalBody({ state }: Props) {
    const { t } = useTranslation('common');

    if (state.status === 'loading') {
        return <LegalBodySkeleton />;
    }

    if (state.status === 'failed') {
        return (
            <p role="alert" className="min-h-[50svh] text-base text-muted-foreground">
                {t('legal.unavailable')}
            </p>
        );
    }

    return (
        <div className="grid gap-8 lg:grid-cols-[13rem_minmax(0,1fr)] lg:items-start lg:gap-14">
            <LegalToc sections={state.content.sections} />

            <LegalMarkdown body={state.content.body} markerPrefix={t('legal.placeholderPrefix')} />
        </div>
    );
}
