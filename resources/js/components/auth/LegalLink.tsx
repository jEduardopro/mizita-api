import type { ReactNode } from 'react';

/**
 * The anchor `Trans` clones for each tag in a legal sentence. It carries no text
 * of its own: the label comes from inside the translated string, which is the
 * only place a translator can reach it.
 *
 * Shared rather than copied because both auth screens now set the same sentence
 * — registration under its card, login at the foot of its sheet — and the two
 * would have drifted the first time either one was restyled.
 *
 * PLACEHOLDER: the destination is `#`, matching `PublicLayout`'s footer. The
 * terms and privacy pages are routes `mizita-backend` has yet to add.
 */
export function LegalLink({ children }: { children?: ReactNode }) {
    return (
        <a
            href="#"
            className="rounded-sm underline underline-offset-2 transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
        >
            {children}
        </a>
    );
}
