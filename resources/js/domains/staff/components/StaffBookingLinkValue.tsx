import { cn } from 'cn';
import { Copy, Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { withoutProtocol } from './booking-link';
import { ICON_BUTTON, INLINE_ACTION } from './inline-action';

type Props = {
    url: string;
    onCopy: () => void;
    onEdit?: () => void;
};

export function StaffBookingLinkValue({ url, onCopy, onEdit }: Props) {
    const { t } = useTranslation('admin');

    return (
        <div className="flex flex-wrap items-center gap-x-1">
            <a href={url} target="_blank" rel="noreferrer" className={cn(INLINE_ACTION, 'mr-1 min-w-0 break-all')}>
                {withoutProtocol(url)}
                <span className="sr-only"> {t('profile.bookingLink.opensInNewTab')}</span>
            </a>

            <Button
                type="button"
                variant="ghost"
                onClick={onCopy}
                aria-label={t('profile.bookingLink.copy')}
                className={ICON_BUTTON}
            >
                <Copy aria-hidden="true" />
            </Button>

            {onEdit === undefined ? null : (
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onEdit}
                    aria-label={t('profile.bookingLink.edit')}
                    className={ICON_BUTTON}
                >
                    <Pencil aria-hidden="true" />
                </Button>
            )}
        </div>
    );
}
