import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Wordmark } from '@/components/shared/Wordmark';
import { useFlashToast } from '@/hooks/use-flash-toast';

type Props = {
    title: string;
    heading: string;
    description: string;
    children: ReactNode;
    footer?: ReactNode;
};

export function AuthLayout({ title, heading, description, children, footer }: Props) {
    const { name } = usePage().props;

    useFlashToast();

    return (
        <div className="flex min-h-svh flex-col items-center bg-background px-5 py-12 text-foreground sm:py-20">
            <Head title={title} />

            <div className="w-full max-w-sm">
                <Wordmark name={name} size="sm" />

                <div className="mt-10 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-1 motion-safe:duration-300">
                    <h1 className="font-heading text-2xl font-medium tracking-[-0.03em]">
                        {heading}
                    </h1>
                    <p className="mt-1.5 text-sm text-muted-foreground">{description}</p>

                    <div className="mt-7">{children}</div>

                    {footer ? (
                        <div className="mt-7 border-t border-border pt-5 text-sm text-muted-foreground">
                            {footer}
                        </div>
                    ) : null}
                </div>
            </div>
        </div>
    );
}
