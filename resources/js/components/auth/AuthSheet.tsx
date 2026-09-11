import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SupportPhoneLink } from '@/components/auth/SupportPhoneLink';
import { Wordmark } from '@/components/shared/Wordmark';

type Props = {
    /** What the sheet is for, in the words the visitor came here with. */
    heading: string;
    /** One line on what happens next. Optional: not every screen owes an answer. */
    description?: string;
    /** The panel currently being asked for. */
    children: ReactNode;
    /** The way to the other auth screen, set off by a rule. */
    footer: ReactNode;
    /** The terms, the privacy notice and the copyright, anchored to the floor. */
    legal: ReactNode;
};

/**
 * The full-height white sheet the login screen is drawn on: the brand row at the
 * top, the panel centred in what is left, and the legal line at the foot.
 *
 * It is a sibling of `AuthCard`, not a variant of it. The card is a box that
 * hugs its contents and lives inside a page that has a header; the sheet *is*
 * the page's chrome — it carries the wordmark and the phone number that the
 * header used to, and it runs from the top of the window to the bottom. Giving
 * `AuthCard` a flag to switch between the two would put both silhouettes behind
 * one entry point that registration would then have to keep passing `false` to.
 *
 * Like the card, it is the chrome only. What it asks — a choice of method, a
 * form, a status message — belongs to whoever renders it.
 */
export function AuthSheet({ heading, description, children, footer, legal }: Props) {
    const { name } = usePage().props;

    return (
        <div className="flex flex-1 flex-col rounded-2xl border border-border bg-card p-6 shadow-xl shadow-foreground/5 sm:p-8 dark:shadow-black/30">
            <div className="flex items-center justify-between gap-4">
                <Wordmark name={name} size="lg" />
                <SupportPhoneLink />
            </div>

            {/*
             * `justify-center` is what absorbs the height change when the panel
             * swaps: the block grows from its middle, so the brand row above it
             * and the legal line below it stay exactly where they were. The
             * sheet's own height is what pays for it, so no panel ever has to be
             * measured into a magic minimum.
             */}
            <div className="flex flex-1 flex-col justify-center py-10">
                <h2 className="font-heading text-2xl font-medium tracking-[-0.02em]">{heading}</h2>

                {description ? (
                    <p className="mt-1.5 text-sm text-muted-foreground">{description}</p>
                ) : null}

                <div className="mt-6">{children}</div>

                <p className="mt-6 border-t border-border pt-5 text-center text-sm text-muted-foreground">
                    {footer}
                </p>
            </div>

            <div className="text-xs leading-relaxed text-muted-foreground">{legal}</div>
        </div>
    );
}
