import { cn } from 'cn';
import { useTranslation } from 'react-i18next';

/** `href` is the E.164 digits a dialler needs, `label` the form a person reads. */
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
        // A bare string of digits is not an accessible name, so the link is
        // labelled with the whole sentence and shows only the number.
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
