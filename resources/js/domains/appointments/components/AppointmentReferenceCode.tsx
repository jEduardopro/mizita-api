import { Copy } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useCopyToClipboard } from '@/hooks/use-copy-to-clipboard';

type Props = {
    code: string;
};

export function AppointmentReferenceCode({ code }: Props) {
    const { t } = useTranslation('admin');

    const copy = useCopyToClipboard({
        copied: t('calendar.appointment.details.referenceCopied'),
        failed: t('calendar.appointment.details.referenceCopyFailed'),
    });

    return (
        <div className="grid gap-0.5">
            <p className="text-muted-foreground">{t('calendar.appointment.details.reference')}</p>

            <div className="flex items-center gap-1">
                <p className="min-w-0 font-mono text-base font-medium tracking-[0.15em] break-all">{code}</p>

                <Button
                    type="button"
                    variant="ghost"
                    onClick={() => copy(code)}
                    className="size-11 shrink-0 p-0 md:size-9"
                >
                    <Copy aria-hidden="true" />

                    <span className="sr-only">{t('calendar.appointment.details.copyReference')}</span>
                </Button>
            </div>
        </div>
    );
}
