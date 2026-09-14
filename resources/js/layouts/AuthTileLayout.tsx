import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { TileBackdrop } from '@/components/auth/TileBackdrop';

type Props = {
    title: string;
    children: ReactNode;
};

export function AuthTileLayout({ title, children }: Props) {
    return (
        <div className="relative min-h-svh text-foreground">
            <Head title={title} />

            <TileBackdrop />

            <main className="flex min-h-svh w-full items-stretch p-3 sm:p-5 lg:p-6">
                <div className="flex w-full flex-col lg:w-[28rem] lg:shrink-0 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-3 motion-safe:duration-700">
                    {children}
                </div>
            </main>
        </div>
    );
}
