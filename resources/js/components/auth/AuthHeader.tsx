import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SupportPhoneLink } from '@/components/auth/SupportPhoneLink';
import { Wordmark } from '@/components/shared/Wordmark';

type Props = {
    /** The way out of this screen, as a slot so the header never learns which
     * screen is rendering it. */
    action: ReactNode;
};

export function AuthHeader({ action }: Props) {
    const { name } = usePage().props;

    return (
        <header className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
            <Wordmark name={name} size="lg" />

            <div className="flex items-center gap-2 sm:gap-4">
                {/* The number is the first thing to go when the header runs out of
                    room: the action beside it is why anyone is on this page. */}
                <SupportPhoneLink className="hidden sm:inline-flex" />

                {action}
            </div>
        </header>
    );
}
