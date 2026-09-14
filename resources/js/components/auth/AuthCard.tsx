import type { ReactNode } from 'react';

type Props = {
    heading: string;
    description?: string;
    children: ReactNode;
    footer: ReactNode;
};

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
