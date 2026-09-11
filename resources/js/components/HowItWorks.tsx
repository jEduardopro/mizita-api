import { useTranslation } from 'react-i18next';
import { Section } from '@/components/Section';

/**
 * The three steps between signing up and running a full day.
 *
 * They are numbered because they are genuinely ordered — nothing can be shared
 * before it exists, and nothing lands on the agenda before it is shared. The
 * number sits on a hairline rule, the same construction the agenda column uses
 * for an hour label, so the sequence reads as a timeline rather than as a badge.
 */
const steps = ['setUp', 'share', 'run'] as const;

/**
 * Exported so the header menu and this section can never point at two different
 * strings. It is also what `Section` builds the heading id from, so the section
 * takes its accessible name from the heading a visitor can actually read.
 */
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
                        {/*
                         * The number takes the brand colour and the rule fades out
                         * of it, so the ordinal is what the eye finds first and the
                         * timeline still ends in the same hairline as the rest of
                         * the page.
                         */}
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
