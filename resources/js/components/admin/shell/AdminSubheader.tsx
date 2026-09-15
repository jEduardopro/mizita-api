import type { ReactNode } from 'react';

type Props = {
    description?: string;
    actions?: ReactNode;
};

export function AdminSubheader({ description, actions }: Props) {
    if (! description && ! actions) {
        return null;
    }

    return (
        <div className="flex min-h-14 shrink-0 items-center gap-3 border-b border-border px-5 py-2 sm:gap-4 sm:px-8">
            {description ? (
                <p className="line-clamp-2 min-w-0 flex-1 text-sm leading-snug text-pretty text-muted-foreground">
                    {description}
                </p>
            ) : null}

            {actions ? (
                <div className="ml-auto flex shrink-0 items-center gap-2">{actions}</div>
            ) : null}
        </div>
    );
}
