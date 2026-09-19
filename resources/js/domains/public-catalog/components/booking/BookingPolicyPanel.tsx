import { useTranslation } from 'react-i18next';

type Props = {
    message: string;
};

export function BookingPolicyPanel({ message }: Props) {
    const { t } = useTranslation('public');

    return (
        <section className="grid gap-1.5 rounded-xl bg-muted/40 p-4 sm:p-5">
            <h2 className="text-[0.6875rem] font-medium tracking-[0.1em] text-muted-foreground uppercase">
                {t('booking.policy.title')}
            </h2>

            <p className="text-sm leading-relaxed whitespace-pre-line text-muted-foreground text-pretty">
                {message}
            </p>
        </section>
    );
}
