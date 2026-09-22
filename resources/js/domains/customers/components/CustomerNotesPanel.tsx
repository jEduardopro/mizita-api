import { useTranslation } from 'react-i18next';

type Props = {
    notes: string | null;
};

export function CustomerNotesPanel({ notes }: Props) {
    const { t } = useTranslation('admin');

    const written = notes?.trim() ?? '';

    if (written === '') {
        return (
            <section className="rounded-2xl border border-border bg-card p-5 sm:p-6">
                <p className="text-sm text-muted-foreground">{t('customers.show.notes.empty')}</p>
            </section>
        );
    }

    return (
        <section className="rounded-2xl border border-border bg-card p-5 sm:p-6">
            <p className="text-sm whitespace-pre-line text-pretty">{written}</p>
        </section>
    );
}
