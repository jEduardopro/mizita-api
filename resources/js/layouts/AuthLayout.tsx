import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Wordmark } from '@/components/Wordmark';

type Props = {
    /** The tab title. The app name is appended by the title callback in app.tsx. */
    title: string;
    /** The heading shown above the form. */
    heading: string;
    /** One line telling the visitor what this screen will do for them. */
    description: string;
    children: ReactNode;
    /** The link out of this screen — usually to the opposite auth action. */
    footer?: ReactNode;
};

/**
 * The shell for login, registration and password recovery: one column, one task,
 * nothing else competing for attention.
 */
export function AuthLayout({ title, heading, description, children, footer }: Props) {
    const { name } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col items-center bg-background px-5 py-12 text-foreground sm:py-20">
            <Head title={title} />

            <div className="w-full max-w-sm">
                <Wordmark name={name} className="text-sm" />

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
