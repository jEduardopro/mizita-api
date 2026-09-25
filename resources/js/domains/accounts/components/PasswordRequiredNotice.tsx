import { KeyRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

type Props = {
    onCreatePassword: () => void;
};

export function PasswordRequiredNotice({ onCreatePassword }: Props) {
    const { t } = useTranslation('admin');

    return (
        <section className="grid gap-3 rounded-lg bg-muted px-4 py-4 sm:grid-cols-[auto_minmax(0,1fr)] sm:gap-x-3">
            <KeyRound aria-hidden="true" className="size-5 text-foreground/80 sm:mt-0.5" />

            <div className="grid justify-items-start gap-2">
                <h4 className="text-sm font-semibold">{t('security.passwordRequired.title')}</h4>

                <p className="text-sm text-pretty text-foreground/80">{t('security.passwordRequired.body')}</p>

                <Button
                    type="button"
                    variant="outline"
                    onClick={onCreatePassword}
                    className="mt-1 h-11 rounded-full bg-background px-5 md:h-9"
                >
                    {t('security.passwordRequired.action')}
                </Button>
            </div>
        </section>
    );
}
