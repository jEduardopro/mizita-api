import { usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { WordmarkMark } from '@/components/shared/Wordmark';

type Props = {
    heading: string;
    description: string;
    children: ReactNode;
    footer?: ReactNode;
};

export function PlatformAuthSheet({ heading, description, children, footer }: Props) {
    const { t } = useTranslation('platform');
    const { name } = usePage().props;

    return (
        <div className="flex flex-1 flex-col rounded-2xl border border-border bg-card p-6 shadow-xl shadow-foreground/5 sm:p-8 dark:shadow-black/30">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <WordmarkMark name={name} size="lg" />

                <span className="inline-flex items-center gap-1.5 rounded-full bg-foreground px-2.5 py-1 text-[0.625rem] font-medium tracking-[0.12em] text-background uppercase">
                    <ShieldCheck aria-hidden="true" className="size-3" />
                    {t('marker')}
                </span>
            </div>

            <div className="flex flex-1 flex-col justify-center py-10">
                <h2 className="font-heading text-2xl font-medium tracking-[-0.02em]">{heading}</h2>

                <p className="mt-1.5 text-sm text-pretty text-muted-foreground">{description}</p>

                <div className="mt-6">{children}</div>

                {footer ? (
                    <p className="mt-6 border-t border-border pt-5 text-center text-sm text-muted-foreground">
                        {footer}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
