import { useTranslation } from 'react-i18next';
import { Section } from '@/components/public/landing/Section';

/** Numbered because they are genuinely ordered, not for decoration. */
const steps = ['setUp', 'share', 'run'] as const;

/** Exported for the header menu, so the anchor is written down once. */
export const HOW_IT_WORKS_ID = 'how-it-works';

export function HowItWorks() {
    const { t } = useTranslation('public');

    return (
        <Section id={HOW_IT_WORKS_ID} tone="muted">
            <p className="text-[0.6875rem] font-medium tracking-[0.18em] text-primary uppercase">
                {t('welcome.steps.eyebrow')}
            </p>

            <h2
                id={`${HOW_IT_WORKS_ID}-heading`}
                className="mt-4 max-w-xl font-heading text-[clamp(1.75rem,4vw,2.5rem)] leading-[1.05] font-medium tracking-[-0.035em] text-balance"
            >
                {t('welcome.steps.heading')}
            </h2>

            <ol className="mt-10 grid gap-9 sm:mt-12 md:grid-cols-3 md:gap-8">
                {steps.map((step, index) => (
                    <li key={step}>
                        <div className="flex items-center gap-3">
                            <span className="text-sm leading-none font-medium tracking-[0.08em] tabular-nums text-primary">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <span className="h-px flex-1 bg-gradient-to-r from-primary/40 to-border" />
                        </div>

                        <h3 className="mt-4 font-heading text-base leading-snug font-medium tracking-[-0.015em]">
                            {t(`welcome.steps.${step}.title`)}
                        </h3>

                        <p className="mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">
                            {t(`welcome.steps.${step}.body`)}
                        </p>
                    </li>
                ))}
            </ol>
        </Section>
    );
}
