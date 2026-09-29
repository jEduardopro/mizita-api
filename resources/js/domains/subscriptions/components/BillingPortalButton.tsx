import { CreditCard, LoaderCircle } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast } from '@/lib/toast';
import { useOpenBillingPortal } from '../queries';

type Props = {
    label: string;
    variant?: ComponentProps<typeof Button>['variant'];
};

export function BillingPortalButton({ label, variant = 'outline' }: Props) {
    const { t } = useTranslation('admin');
    const portal = useOpenBillingPortal();
    const isOpening = portal.isPending;

    async function openPortal() {
        try {
            const session = await portal.mutateAsync();

            window.location.assign(session.url);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('plan.subscription.portalFailed')));
        }
    }

    return (
        <Button
            type="button"
            variant={variant}
            disabled={isOpening}
            aria-busy={isOpening}
            onClick={() => void openPortal()}
            className="h-11 px-4 md:h-9"
        >
            {isOpening ? (
                <LoaderCircle aria-hidden="true" className="motion-safe:animate-spin" />
            ) : (
                <CreditCard aria-hidden="true" />
            )}
            {isOpening ? t('plan.settings.openingPortal') : label}
        </Button>
    );
}
