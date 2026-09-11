import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SupportPhoneLink } from '@/components/auth/SupportPhoneLink';
import { Wordmark } from '@/components/shared/Wordmark';

type Props = {
    /**
     * The header's right-hand action — the way out of this screen, which is
     * always the opposite of what the card is for. It is a slot rather than a
     * fixed button so each screen hands back its own label without this header
     * learning which screen is rendering it.
     */
    action: ReactNode;
};

/**
 * The bar every auth shell wears: the wordmark, the number to call, and the way
 * out of this screen.
 *
 * It is shared rather than copied because more than one shell draws it, and the
 * phone number is the kind of thing that gets changed in one place and missed in
 * the other — which is also why the number itself now lives in
 * `SupportPhoneLink`, where the login sheet can reach it without a header.
 */
export function AuthHeader({ action }: Props) {
    const { name } = usePage().props;

    return (
        <header className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
            <Wordmark name={name} size="lg" />

            <div className="flex items-center gap-2 sm:gap-4">
                {/*
                 * The number is the first thing to go when the header runs out
                 * of room: the action beside it is why anyone is on this page.
                 */}
                <SupportPhoneLink className="hidden sm:inline-flex" />

                {action}
            </div>
        </header>
    );
}
