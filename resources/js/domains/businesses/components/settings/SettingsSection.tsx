import type { ReactNode } from 'react';

type Props = {
    id: string;
    title: string;
    description: string;
    children: ReactNode;
};

export function SettingsSection({ id, title, description, children }: Props) {
    return (
        <section
            id={id}
            tabIndex={-1}
            aria-labelledby={`${id}-title`}
            className="scroll-mt-44 rounded-2xl border border-border bg-card p-5 outline-none sm:p-6 md:scroll-mt-36"
        >
            <h2 id={`${id}-title`} className="text-base font-semibold sm:text-lg">
                {title}
            </h2>

            <p className="mt-1 text-sm text-pretty text-muted-foreground">{description}</p>

            <div className="mt-5 grid gap-5">{children}</div>
        </section>
    );
}
