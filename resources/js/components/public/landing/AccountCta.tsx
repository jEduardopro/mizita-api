import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

type Props = {
    /**
     * Matches the surrounding type scale: `xl` in a hero or a closing band, where
     * the button is the loudest thing on the screen, `default` inside a card.
     */
    size?: 'default' | 'lg' | 'xl';
    /**
     * Whether logging in is offered next to creating an account. A pricing card
     * is already asking one question — which plan — so it drops the second
     * destination rather than repeating it on every card.
     */
    showLogIn?: boolean;
    /**
     * Overrides the label of the primary action, for a surface that names what the
     * visitor is starting rather than what they are creating — a pricing card, or a
     * hero. Pre-translated, because the caller owns the namespace the copy lives in.
     */
    label?: string;
    /**
     * How loud the primary action is. It defaults to the solid brand fill, and a
     * surface that offers two of these at once — the pricing cards — quiets the
     * secondary one to the outline so the pair reads as a choice rather than as
     * two equal demands. It never touches the log-in button, which is outlined
     * wherever it appears.
     */
    variant?: 'brand' | 'brand-outline';
    className?: string;
};

/**
 * The account call to action, in one place because the landing page makes the
 * same offer twice and the two must never drift.
 *
 * An account belongs to a business, never to the person booking, so this is the
 * only pair of destinations it ever offers: register, or log in. It never reacts
 * to the visitor's session — the landing page is marketing copy and makes the
 * same offer to everyone, signed in or not.
 *
 * The hierarchy between the two is carried by the variants: creating an account
 * is the page's single loudest element, so it takes the solid brand fill, and
 * logging in sits beside it in the same blue but outlined — near enough to read
 * as a pair, quiet enough that it never competes for the first click.
 */
export function AccountCta({
    size = 'default',
    showLogIn = true,
    label,
    variant = 'brand',
    className,
}: Props) {
    const { t } = useTranslation('common');

    return (
        <div className={cn('flex flex-wrap items-center gap-3', className)}>
            <Button asChild variant={variant} size={size}>
                <Link href="/register">{label ?? t('nav.createAccount')}</Link>
            </Button>
            {showLogIn ? (
                <Button asChild variant="brand-outline" size={size}>
                    <Link href="/login">{t('nav.logIn')}</Link>
                </Button>
            ) : null}
        </div>
    );
}
