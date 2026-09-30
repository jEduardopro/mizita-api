import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { usePolicyNoticeDismissal } from './use-policy-notice-dismissal';

type Props = {
    businessId: string;
    message: string;
};

export function BookingPolicyNotice({ businessId, message }: Props) {
    const { t } = useTranslation('public');
    const headingId = useId();
    const { dismissed, dismiss } = usePolicyNoticeDismissal(businessId, message);

    if (dismissed) {
        return null;
    }

    return (
        <section aria-labelledby={headingId} className="grid gap-2 rounded-2xl bg-muted p-5 sm:p-6">
            <h2 id={headingId} className="text-sm font-semibold">
                {t('booking.policy.dialogTitle')}
            </h2>

            <p className="text-sm leading-relaxed text-pretty whitespace-pre-line text-foreground">
                {message}
            </p>

            <div className="mt-2 flex justify-end">
                <Button type="button" variant="outline" className="h-11 rounded-full px-5" onClick={dismiss}>
                    {t('booking.policy.acknowledge')}
                </Button>
            </div>
        </section>
    );
}
