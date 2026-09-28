import { CalendarClock } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type Props = {
    planLabel: string;
    endsOn: string | null;
};

export function CurrentPlanSummary({ planLabel, endsOn }: Props) {
    const { t } = useTranslation('admin');

    return (
        <header className="grid content-start gap-3">
            <p className="text-[0.6875rem] font-medium tracking-[0.18em] text-primary uppercase">
                {t('plan.settings.eyebrow')}
            </p>

            <h2 className="max-w-md font-heading text-[clamp(1.625rem,3.5vw,2.25rem)] leading-[1.08] font-medium tracking-[-0.035em] text-balance">
                {t('plan.settings.current', { plan: planLabel })}
            </h2>

            <p className="max-w-md text-sm leading-relaxed text-pretty text-muted-foreground">
                {t('plan.settings.subtitle')}
            </p>

            {endsOn !== null ? (
                <p className="mt-1 inline-flex items-center gap-2 text-sm font-medium text-foreground">
                    <CalendarClock aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />
                    {t('plan.settings.endsAt', { date: endsOn })}
                </p>
            ) : null}
        </header>
    );
}
