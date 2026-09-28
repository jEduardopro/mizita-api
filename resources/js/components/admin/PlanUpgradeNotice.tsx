import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { ArrowRight, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PLAN_SETTINGS_HREF } from '@/lib/plan';

type Props = {
    description?: string;
    className?: string;
};

export function PlanUpgradeNotice({ description, className }: Props) {
    const { t } = useTranslation('admin');

    return (
        <div
            role="note"
            className={cn(
                'flex flex-col gap-1 rounded-lg border border-border bg-muted/60 px-4 py-3 text-sm sm:flex-row sm:items-center sm:gap-4',
                className,
            )}
        >
            <div className="flex min-w-0 flex-1 gap-2.5">
                <Sparkles aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-foreground/70" />

                <div className="grid min-w-0 gap-0.5">
                    <p className="font-medium text-foreground">{t('plan.notice.title')}</p>

                    {description !== undefined && (
                        <p className="leading-relaxed text-pretty text-muted-foreground">{description}</p>
                    )}
                </div>
            </div>

            <Link
                href={PLAN_SETTINGS_HREF}
                className="inline-flex min-h-11 shrink-0 items-center gap-1.5 self-start rounded-md pl-6.5 font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50 sm:self-center sm:pl-0 md:min-h-9"
            >
                {t('plan.notice.link')}
                <ArrowRight aria-hidden="true" className="size-3.5" />
            </Link>
        </div>
    );
}
