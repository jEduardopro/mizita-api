import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

type Props = {
    planLabel: string;
    details?: ReactNode;
};

export function CurrentPlanSummary({ planLabel, details }: Props) {
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

            {details ? <div className="mt-1 grid justify-items-start gap-3">{details}</div> : null}
        </header>
    );
}
