import { cn } from 'cn';
import { useTranslation } from 'react-i18next';

type Props = {
    message: string;
    surfaceClassName: string;
};

export function BookingPagePreviewPolicy({ message, surfaceClassName }: Props) {
    const { t } = useTranslation('admin');

    const policy = message.trim();

    if (policy === '') {
        return null;
    }

    return (
        <p className={cn('line-clamp-3 px-4 py-2.5 text-xs text-pretty', surfaceClassName)}>
            <span className="font-semibold">{t('businessSettings.preview.policyTitle')} </span>

            {policy}
        </p>
    );
}
