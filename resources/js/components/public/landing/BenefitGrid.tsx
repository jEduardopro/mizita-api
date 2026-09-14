import { useTranslation } from 'react-i18next';
import { Section } from '@/components/public/landing/Section';

const benefits = ['team', 'hours', 'slots', 'policy'] as const;

export const BENEFITS_ID = 'what-you-get';

export function BenefitGrid() {
    const { t } = useTranslation('public');

    return (
        <Section id={BENEFITS_ID}>
            <p className="text-[0.6875rem] font-medium tracking-[0.18em] text-primary uppercase">
                {t('welcome.benefits.eyebrow')}
            </p>

            <h2
                id={`${BENEFITS_ID}-heading`}
                className="mt-4 max-w-xl font-heading text-[clamp(1.75rem,4vw,2.5rem)] leading-[1.05] font-medium tracking-[-0.035em] text-balance"
            >
                {t('welcome.benefits.heading')}
            </h2>

            <div className="mt-10 grid gap-px overflow-hidden rounded-xl bg-border p-px sm:mt-12 sm:grid-cols-2">
                {benefits.map((benefit) => (
                    <article
                        key={benefit}
                        className="group bg-card p-5 transition-colors hover:bg-brand-50/60 sm:p-6 dark:hover:bg-brand-950/30"
                    >
                        <p className="flex items-center gap-2 text-[0.6875rem] tracking-[0.14em] text-primary uppercase">
                            <span aria-hidden="true" className="size-1.5 shrink-0 rounded-[2px] bg-primary" />
                            {t(`welcome.benefits.${benefit}.term`)}
                        </p>

                        <h3 className="mt-3 font-heading text-base leading-snug font-medium tracking-[-0.015em]">
                            {t(`welcome.benefits.${benefit}.title`)}
                        </h3>

                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            {t(`welcome.benefits.${benefit}.body`)}
                        </p>
                    </article>
                ))}
            </div>
        </Section>
    );
}
