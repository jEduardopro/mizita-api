import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { legalDocuments } from '@/content/legal/entity';

type Props = {
    /**
     * Which document the sentence is pointing at. Only two of the three are ever
     * named in an auth screen: signing up accepts the terms and acknowledges the
     * privacy notice, and the cookie policy is reached from the footer.
     */
    document: 'terms' | 'privacy';
    children?: ReactNode;
};

/**
 * The anchor `Trans` clones for each tag in a legal sentence. It carries no text
 * of its own: the label comes from inside the translated string, which is the
 * only place a translator can reach it.
 *
 * Shared rather than copied because both auth screens set the same sentence —
 * registration under its card, login at the foot of its sheet — and the two
 * would have drifted the first time either one was restyled.
 *
 * The destination comes from `legalDocuments` rather than from a literal here,
 * so the path is declared in the same file as the documents themselves.
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
