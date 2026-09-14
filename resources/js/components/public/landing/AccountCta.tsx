import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

type Props = {
    size?: 'default' | 'lg' | 'xl';
    showLogIn?: boolean;
    /** Pre-translated, because the caller owns the namespace the copy lives in. */
    label?: string;
    /** Only the primary action. The log-in button is outlined wherever it appears. */
    variant?: 'brand' | 'brand-outline';
    className?: string;
};

/**
 * It never reacts to the visitor's session: the landing page is marketing copy
 * and makes the same offer to everyone, signed in or not.
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
