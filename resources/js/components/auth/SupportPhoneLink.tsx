import { cn } from 'cn';
import { useTranslation } from 'react-i18next';

const supportPhone = {
    href: 'tel:+528421133477',
    label: '+52 842 113 3477',
};

type Props = {
    className?: string;
};

export function SupportPhoneLink({ className }: Props) {
    const { t } = useTranslation('auth');

    return (
        <a
            href={supportPhone.href}
            aria-label={t('shell.callUs', { phone: supportPhone.label })}
            className={cn(
                'rounded-md text-sm font-medium tabular-nums text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50',
                className,
            )}
        >
            {supportPhone.label}
        </a>
    );
}
