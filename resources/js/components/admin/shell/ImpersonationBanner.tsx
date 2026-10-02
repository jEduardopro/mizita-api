import { VenetianMask } from 'lucide-react';
import { Trans, useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useImpersonationCountdown } from '@/hooks/use-impersonation-countdown';
import { useStopImpersonation } from '@/hooks/use-stop-impersonation';

type Props = {
    businessName: string;
    ownerName: string;
    expiresAt: string;
};

export function ImpersonationBanner({ businessName, ownerName, expiresAt }: Props) {
    const { t } = useTranslation('admin');
    const minutesLeft = useImpersonationCountdown(expiresAt);
    const stopImpersonation = useStopImpersonation();

    const countdown =
        minutesLeft > 0
            ? t('impersonation.expiresIn', { count: minutesLeft })
            : t('impersonation.ending');

    return (
        <div
            role="status"
            aria-atomic="false"
            className="border-b border-warning/30 bg-warning-surface text-foreground"
        >
            <div
                aria-hidden="true"
                className="h-1 bg-[repeating-linear-gradient(135deg,var(--warning)_0_8px,transparent_8px_16px)] opacity-60"
            />

            <div className="flex items-center gap-3 px-3 py-1.5 sm:px-5">
                <VenetianMask aria-hidden="true" className="size-4 shrink-0 text-warning" />

                <p className="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-2 gap-y-0.5 text-sm text-pretty">
                    <span className="min-w-0 break-words">
                        <Trans
                            i18nKey="impersonation.viewing"
                            ns="admin"
                            values={{ business: businessName, owner: ownerName }}
                            components={{ strong: <strong className="font-semibold" /> }}
                        />
                    </span>

                    <span className="text-xs font-medium text-muted-foreground tabular-nums">
                        {countdown}
                    </span>
                </p>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={stopImpersonation}
                    aria-label={t('impersonation.stop')}
                    className="min-h-11 shrink-0 border-warning/40 px-3 md:min-h-7"
                >
                    {t('impersonation.exit')}
                </Button>
            </div>
        </div>
    );
}
