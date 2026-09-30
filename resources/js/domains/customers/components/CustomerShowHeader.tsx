import type { ReactNode } from 'react';
import { CustomerAvatar } from './CustomerAvatar';

type Props = {
    name: string;
    photoUrl: string | null;
    lastAppointment?: ReactNode;
    actions: ReactNode;
};

export function CustomerShowHeader({ name, photoUrl, lastAppointment, actions }: Props) {
    return (
        <header className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-6">
            <div className="flex min-w-0 items-center gap-3">
                <CustomerAvatar name={name} photoUrl={photoUrl} size="lg" />

                <div className="grid min-w-0 gap-0.5">
                    <p className="min-w-0 text-xl font-semibold tracking-tight text-balance sm:text-2xl">
                        {name}
                    </p>

                    {lastAppointment}
                </div>
            </div>

            {actions}
        </header>
    );
}
