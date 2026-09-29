import { CalendarCheck, CalendarClock, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BillingPortalButton } from './BillingPortalButton';
import { ResumeSubscriptionButton } from './ResumeSubscriptionButton';
import type { SubscriptionStanding } from './subscription-standing';

type Props = {
    standing: SubscriptionStanding;
    canChangeCard: boolean;
    formatDate: (instant: string) => string;
};

type StandingLineProps = {
    icon: LucideIcon;
    text: string;
};

function StandingLine({ icon: Icon, text }: StandingLineProps) {
    return (
        <p className="inline-flex items-center gap-2 text-sm font-medium text-foreground">
            <Icon aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />
            {text}
        </p>
    );
}

export function SubscriptionStandingDetails({ standing, canChangeCard, formatDate }: Props) {
    const { t } = useTranslation('admin');

    if (standing.kind === 'ending') {
        return (
            <>
                <StandingLine
                    icon={CalendarClock}
                    text={t('plan.settings.accessUntil', { date: formatDate(standing.accessEndsAt) })}
                />

                <ResumeSubscriptionButton />
            </>
        );
    }

    if (standing.kind === 'renewing') {
        return (
            <>
                <StandingLine
                    icon={CalendarCheck}
                    text={t('plan.settings.renewsAt', { date: formatDate(standing.renewsAt) })}
                />

                {canChangeCard ? <BillingPortalButton label={t('plan.settings.changeCard')} /> : null}
            </>
        );
    }

    return null;
}
