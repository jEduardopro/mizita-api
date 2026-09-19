import { Copy } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';

type Props = {
    code: string;
};

export function BookingReferenceCopy({ code }: Props) {
    const { t } = useTranslation('public');

    async function copyCode() {
        try {
            await navigator.clipboard.writeText(code);
            raiseSuccessToast(
                t('booking.flow.confirmed.copied', { defaultValue: 'Reference copied' }),
            );
        } catch {
            raiseErrorToast(
                t('booking.flow.confirmed.copyFailed', {
                    defaultValue: 'We could not copy the reference. Write it down.',
                }),
            );
        }
    }

    return (
        <div className="flex items-center justify-between gap-2 rounded-xl border border-dashed border-border bg-muted/40 py-1 pr-1 pl-3">
            <p className="min-w-0 font-mono text-sm tracking-[0.12em] break-all uppercase">
                {t('booking.flow.confirmed.reference', { code })}
            </p>

            <Button
                type="button"
                variant="ghost"
                onClick={() => void copyCode()}
                className="size-11 shrink-0 p-0"
            >
                <Copy aria-hidden="true" />

                <span className="sr-only">
                    {t('booking.flow.confirmed.copy', { defaultValue: 'Copy the reference' })}
                </span>
            </Button>
        </div>
    );
}
