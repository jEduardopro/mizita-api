import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AuthHeader } from '@/components/auth/AuthHeader';
import { HeroCollage } from '@/components/shared/hero/HeroCollage';
import { useFlashToast } from '@/hooks/use-flash-toast';

type Props = {
    title: string;
    heading: string;
    description: string;
    action: ReactNode;
    children: ReactNode;
};

export function AuthSplitLayout({ title, heading, description, action, children }: Props) {
    useFlashToast();

    return (
        <div className="flex min-h-svh flex-col bg-surface-muted text-foreground">
            <Head title={title} />

            <AuthHeader action={action} />

            <main className="mx-auto grid w-full max-w-6xl flex-1 content-start items-start gap-10 px-5 pt-4 pb-14 sm:px-8 lg:grid-cols-[1fr_25rem] lg:gap-16 lg:pt-8 lg:pb-20 xl:gap-24">
                <div className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 motion-safe:duration-500">
                    <h1 className="max-w-[15ch] font-heading text-[clamp(2.25rem,5.5vw,3.75rem)] leading-[0.96] font-medium tracking-[-0.045em] text-balance">
                        {heading}
                    </h1>

                    <p className="mt-5 max-w-md text-base leading-relaxed text-muted-foreground">
                        {description}
                    </p>

                    <div className="mt-10 hidden lg:block">
                        <HeroCollage />
                    </div>
                </div>

                <div className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-3 motion-safe:duration-700">
                    {children}
                </div>
            </main>
        </div>
    );
}
