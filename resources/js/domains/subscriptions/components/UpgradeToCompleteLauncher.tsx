import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { raiseSuccessToast } from '@/lib/toast';
import { checkoutOutcomeKey } from './checkout-outcome';
import { SubscriptionCheckoutDialog } from './SubscriptionCheckoutDialog';
import { useStartCheckout } from '../queries';
import type { Subscription } from '../types';

type Props = {
    className?: string;
};

export function UpgradeToCompleteLauncher({ className }: Props) {
    const { t } = useTranslation('admin');
    const [open, setOpen] = useState(false);
    const start = useStartCheckout();

    function openCheckout() {
        setOpen(true);
        start.mutate();
    }

    function closeCheckout() {
        setOpen(false);
        start.reset();
    }

    function handleSubscribed(subscription: Subscription) {
        raiseSuccessToast(t(checkoutOutcomeKey(subscription)));
        closeCheckout();
    }

    return (
        <>
            <Button type="button" variant="brand" size="xl" onClick={openCheckout} className={className}>
                {t('plan.settings.upgrade')}
            </Button>

            <SubscriptionCheckoutDialog
                open={open}
                onClose={closeCheckout}
                checkout={start.data}
                startError={start.error}
                onRetry={() => start.mutate()}
                onSubscribed={handleSubscribed}
            />
        </>
    );
}
