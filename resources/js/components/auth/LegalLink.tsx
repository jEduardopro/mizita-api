import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { legalDocuments } from '@/content/legal/entity';

type Props = {
    document: 'terms' | 'privacy';
    children?: ReactNode;
};

/**
 * The anchor `Trans` clones for each tag in a legal sentence. It carries no text
 * of its own: the label comes from inside the translated string, which is the
 * only place a translator can reach it.
 */
export function LegalLink({ document, children }: Props) {
    return (
        <Link
            href={legalDocuments[document].path}
            className="rounded-sm underline underline-offset-2 transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
        >
            {children}
        </Link>
    );
}
