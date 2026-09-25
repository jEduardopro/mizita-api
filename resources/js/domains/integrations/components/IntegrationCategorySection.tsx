import { useId, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import type { IntegrationCategory } from '../types';
import { CATEGORY_PRESENTATIONS } from './integration-catalog';

type Props = {
    category: IntegrationCategory;
    children: ReactNode;
};

export function IntegrationCategorySection({ category, children }: Props) {
    const { t } = useTranslation('admin');
    const headingId = useId();
    const presentation = CATEGORY_PRESENTATIONS[category];

    return (
        <section aria-labelledby={headingId} className="grid gap-4">
            <div className="grid gap-1">
                <h2 id={headingId} className="text-base font-semibold tracking-tight">
                    {t(presentation.titleKey)}
                </h2>

                <p className="text-sm text-pretty text-muted-foreground">{t(presentation.descriptionKey)}</p>
            </div>

            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{children}</ul>
        </section>
    );
}
