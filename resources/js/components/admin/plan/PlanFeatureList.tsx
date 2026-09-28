import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { PRICING_COMING_SOON_KEY, type PricingPlanFeature } from '@/lib/pricing-plans';

type Props = {
    features: readonly PricingPlanFeature[];
};

export function PlanFeatureList({ features }: Props) {
    const { t } = useTranslation('public');

    return (
        <ul className="mt-4 space-y-2.5">
            {features.map((feature) => (
                <li key={feature.key} className="flex gap-3 text-sm leading-relaxed text-muted-foreground">
                    <span
                        aria-hidden="true"
                        className="mt-[0.4375rem] size-1.5 shrink-0 rounded-[2px] bg-primary/60"
                    />

                    <span className="min-w-0">
                        {t(feature.key)}

                        {feature.comingSoon ? (
                            <Badge
                                variant="outline"
                                className="ml-2 align-[0.0625rem] text-[0.625rem] tracking-[0.12em] text-muted-foreground uppercase"
                            >
                                {t(PRICING_COMING_SOON_KEY)}
                            </Badge>
                        ) : null}
                    </span>
                </li>
            ))}
        </ul>
    );
}
