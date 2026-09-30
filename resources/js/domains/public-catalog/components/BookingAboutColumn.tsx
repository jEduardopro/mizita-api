import type { ReactNode } from 'react';

type Props = {
    title: string;
    children: ReactNode;
};

export function BookingAboutColumn({ title, children }: Props) {
    return (
        <div className="grid content-start gap-2">
            <h3 className="text-sm font-medium text-balance">{title}</h3>

            {children}
        </div>
    );
}
