import type { ReactNode } from 'react';

type Props = {
    /** What the card is for, in the words the visitor came here with. */
    heading: string;
    /** One line on what happens next. Optional: not every card owes an answer. */
    description?: string;
    /** The panel currently being asked for. */
    children: ReactNode;
    /** The way to the other auth screen, set off by a rule. */
    footer: ReactNode;
};

/**
 * The white box the registration card is drawn in: the heading, the panel, and
 * the link out under a hairline. It is as tall as what it holds.
 *
 * It is the chrome only. What the card asks — a choice of method, a form, a
 * status message — belongs to whoever renders it.
 *
 * Login is not drawn here: it gets `AuthSheet`, a full-height sheet that carries
 * the wordmark and the phone number itself. The two are siblings rather than one
 * component with a switch, so neither silhouette can drift into the other.
 */
export function AuthCard({ heading, description, children, footer }: Props) {
    return (
        <div className="rounded-2xl border border-border bg-card p-6 shadow-xl shadow-foreground/5 sm:p-8 dark:shadow-black/30">
            <h2 className="font-heading text-lg font-medium tracking-[-0.02em]">{heading}</h2>

            {description ? (
                <p className="mt-1.5 text-sm text-muted-foreground">{description}</p>
            ) : null}

            <div className="mt-6">{children}</div>

            <p className="mt-6 border-t border-border pt-5 text-center text-sm text-muted-foreground">
                {footer}
            </p>
        </div>
    );
}
