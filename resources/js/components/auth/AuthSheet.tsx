import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SupportPhoneLink } from '@/components/auth/SupportPhoneLink';
import { Wordmark } from '@/components/shared/Wordmark';

type Props = {
    heading: string;
    description?: string;
    children: ReactNode;
    footer: ReactNode;
    legal: ReactNode;
};

/** A sibling of `AuthCard`, not a variant: the sheet is the page's own chrome. */
export function AuthSheet({ heading, description, children, footer, legal }: Props) {
    const { name } = usePage().props;

    return (
        <div className="flex flex-1 flex-col rounded-2xl border border-border bg-card p-6 shadow-xl shadow-foreground/5 sm:p-8 dark:shadow-black/30">
            <div className="flex items-center justify-between gap-4">
                <Wordmark name={name} size="lg" />
                <SupportPhoneLink />
            </div>

            {/* `justify-center` absorbs the height change when the panel swaps,
                so the brand row and the legal line stay put. */}
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
