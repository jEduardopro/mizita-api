import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type Props = {
    message: string;
};

export function BookingPolicyNotice({ message }: Props) {
    const { t } = useTranslation('public');

    return (
        <section className="flex items-start gap-3 rounded-2xl border border-border bg-card p-4 text-card-foreground shadow-sm sm:p-5">
            <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-muted-foreground" />

            <div className="grid min-w-0 gap-1">
                <h2 className="text-sm font-medium">{t('booking.policy.title')}</h2>

                <p className="text-sm leading-relaxed whitespace-pre-line text-muted-foreground">
                    {message}
                </p>
            </div>
        </section>
    );
}
