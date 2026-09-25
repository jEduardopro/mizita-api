import { Fingerprint, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

const PASSKEY_DATE_FORMAT: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
};

type Props = {
    name: string;
    authenticator: string | null;
    createdAt: string;
    lastUsedAt: string | null;
    onDelete: () => void;
};

export function PasskeyRow({ name, authenticator, createdAt, lastUsedAt, onDelete }: Props) {
    const { t, i18n } = useTranslation('admin');
    const dateFormatter = new Intl.DateTimeFormat(i18n.language, PASSKEY_DATE_FORMAT);
    const formatDate = (instant: string) => dateFormatter.format(new Date(instant));

    return (
        <li className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-3 py-4">
            <span className="grid size-10 place-items-center rounded-lg bg-muted">
                <Fingerprint aria-hidden="true" className="size-5 text-foreground/80" />
            </span>

            <div className="grid min-w-0 gap-0.5">
                <p className="text-sm font-medium break-words text-foreground">{name}</p>

                {authenticator === null ? null : (
                    <p className="text-sm break-words text-muted-foreground">{authenticator}</p>
                )}

                <p className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground tabular-nums">
                    <span>{t('security.passkey.created', { date: formatDate(createdAt) })}</span>
                    <span>
                        {lastUsedAt === null
                            ? t('security.passkey.neverUsed')
                            : t('security.passkey.lastUsed', { date: formatDate(lastUsedAt) })}
                    </span>
                </p>
            </div>

            <Button
                type="button"
                variant="ghost"
                onClick={onDelete}
                aria-label={t('security.passkey.deleteLabel', { name })}
                className="-my-1 -mr-3 size-11 p-0 text-muted-foreground hover:text-destructive focus-visible:text-destructive"
            >
                <Trash2 aria-hidden="true" className="size-4" />
            </Button>
        </li>
    );
}
