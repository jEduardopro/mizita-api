import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BillingPortalButton } from './BillingPortalButton';

type Props = {
    graceEndsOn: string;
};

export function PaymentPastDueBanner({ graceEndsOn }: Props) {
    const { t } = useTranslation('admin');

    return (
        <section
            role="alert"
            aria-labelledby="payment-past-due-title"
            className="flex flex-col gap-4 rounded-xl border border-brand-accent-amber/40 bg-brand-accent-amber/10 p-4 sm:flex-row sm:items-center sm:gap-6 sm:p-5"
        >
            <div className="flex min-w-0 flex-1 gap-3">
                <TriangleAlert aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-brand-accent-amber" />

                <div className="grid min-w-0 gap-1">
                    <h2 id="payment-past-due-title" className="text-sm font-medium text-foreground">
                        {t('plan.subscription.pastDue.title')}
                    </h2>

                    <p className="text-sm leading-relaxed text-pretty text-foreground/80">
                        {t('plan.subscription.pastDue.body', { date: graceEndsOn })}
                    </p>
                </div>
            </div>

            <div className="shrink-0 pl-8 sm:pl-0">
                <BillingPortalButton label={t('plan.subscription.pastDue.action')} variant="default" />
            </div>
        </section>
    );
}
