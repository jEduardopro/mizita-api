import type { ReactNode } from 'react';

type Props = {
    children: ReactNode;
};

export function IntegrationLogoTile({ children }: Props) {
    return (
        <span className="grid size-12 shrink-0 place-items-center rounded-xl bg-background ring-1 ring-border [&>svg]:size-7">
            {children}
        </span>
    );
}
