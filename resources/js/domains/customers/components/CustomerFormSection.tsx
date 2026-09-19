import { useId, type ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    children: ReactNode;
};

export function CustomerFormSection({ title, description, children }: Props) {
    const titleId = useId();

    return (
        <section
            aria-labelledby={titleId}
            className="rounded-2xl border border-border bg-card p-5 sm:p-6"
        >
            <h2 id={titleId} className="text-base font-semibold sm:text-lg">
                {title}
            </h2>

            {description !== undefined && (
                <p className="mt-1 text-sm text-pretty text-muted-foreground">{description}</p>
            )}

            <div className="mt-5 grid gap-5">{children}</div>
        </section>
    );
}
