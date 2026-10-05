import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Section } from '@/components/public/landing/Section';

const points = ['businesses', 'customers', 'always'] as const;

export const ABOUT_ID = 'about';

export function AboutPlatform() {
    const { name } = usePage().props;
    const { t } = useTranslation('public');

    return (
        <Section id={ABOUT_ID}>
            <div className="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">
                <div>
                    <p className="text-[0.6875rem] font-medium tracking-[0.18em] text-primary uppercase">
                        {t('welcome.about.eyebrow', { name })}
                    </p>

                    <h2
                        id={`${ABOUT_ID}-heading`}
                        className="mt-4 max-w-xl font-heading text-[clamp(1.75rem,4vw,2.5rem)] leading-[1.05] font-medium tracking-[-0.035em] text-balance"
                    >
                        {t('welcome.about.heading')}
                    </h2>

                    <p className="mt-5 max-w-lg text-base leading-relaxed text-pretty text-muted-foreground">
                        {t('welcome.about.body', { name })}
                    </p>
                </div>

                <dl className="divide-y divide-border border-y border-border lg:self-end">
                    {points.map((point) => (
                        <div
                            key={point}
                            className="grid gap-1.5 py-4 sm:grid-cols-[9rem_minmax(0,1fr)] sm:gap-6"
                        >
                            <dt className="flex items-center gap-2 text-[0.6875rem] font-medium tracking-[0.14em] text-primary uppercase sm:h-[1.375rem]">
                                <span aria-hidden="true" className="size-1.5 shrink-0 rounded-[2px] bg-primary" />
                                {t(`welcome.about.points.${point}.term`)}
                            </dt>

                            <dd className="text-sm leading-relaxed text-foreground">
                                {t(`welcome.about.points.${point}.body`)}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </Section>
    );
}
