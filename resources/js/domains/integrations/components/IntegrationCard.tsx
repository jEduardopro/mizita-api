import type { ReactNode } from 'react';
import type { ConnectionStatus } from '../types';
import { IntegrationLogoTile } from './IntegrationLogoTile';
import { IntegrationStatusBadge } from './IntegrationStatusBadge';

type Props = {
    name: string;
    description: string;
    logo: ReactNode;
    status: ConnectionStatus | null;
    onOpen: () => void;
};

export function IntegrationCard({ name, description, logo, status, onOpen }: Props) {
    return (
        <button
            type="button"
            aria-haspopup="dialog"
            onClick={onOpen}
            className="grid h-full w-full content-start gap-4 rounded-xl border border-border bg-card p-4 text-left text-card-foreground transition-colors outline-none hover:border-foreground/25 hover:bg-muted/40 focus-visible:ring-3 focus-visible:ring-ring/50 motion-reduce:transition-none sm:p-5"
        >
            <span className="flex items-start justify-between gap-3">
                <IntegrationLogoTile>{logo}</IntegrationLogoTile>

                {status === null ? null : <IntegrationStatusBadge status={status} />}
            </span>

            <span className="grid gap-1">
                <span className="font-medium">{name}</span>
                <span className="text-sm text-pretty text-muted-foreground">{description}</span>
            </span>
        </button>
    );
}
