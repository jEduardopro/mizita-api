import type { ReactNode } from 'react';
import { CustomerAvatar } from './CustomerAvatar';

type Props = {
    name: string;
    photoUrl: string | null;
    actions: ReactNode;
};

export function CustomerShowHeader({ name, photoUrl, actions }: Props) {
    return (
        <header className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-6">
            <div className="flex min-w-0 items-center gap-3">
                <CustomerAvatar name={name} photoUrl={photoUrl} size="lg" />

                <p className="min-w-0 text-xl font-semibold tracking-tight text-balance sm:text-2xl">
                    {name}
                </p>
            </div>

            {actions}
        </header>
    );
}
