import { cn } from 'cn';
import { useTranslation } from 'react-i18next';

/**
 * The number the auth screens offer to call.
 *
 * Declared here, the way `PublicLayout` declares its social profiles: one
 * constant for the two forms the same number takes. `href` is the E.164 digits a
 * dialler needs, `label` is the spaced form a person reads, and they can never
 * drift apart because changing the number means editing one object.
 */
const supportPhone = {
    href: 'tel:+528421133477',
    label: '+52 842 113 3477',
};

type Props = {
    className?: string;
};

/**
 * The call-us link, as it appears on every auth screen.
 *
 * It became its own file when a second surface asked for it: the header still
 * draws it beside its action, and the login sheet draws it beside the wordmark.
 * Where it may be dropped is the caller's call — the header hides it on a phone,
 * the sheet never does — so the visibility rule arrives as `className` rather
 * than as a flag this component has to know the screens for.
 */
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
