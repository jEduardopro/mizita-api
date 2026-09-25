import { useTranslation } from 'react-i18next';

const CONNECTED_DATE_FORMAT: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
};

type Props = {
    accountEmail: string;
    connectedAt: string;
};

export function ConnectionAccount({ accountEmail, connectedAt }: Props) {
    const { t, i18n } = useTranslation('admin');
    const connectedOn = new Intl.DateTimeFormat(i18n.language, CONNECTED_DATE_FORMAT).format(new Date(connectedAt));

    return (
        <dl className="grid gap-3 text-sm">
            <div className="grid gap-0.5">
                <dt className="text-muted-foreground">{t('integrations.detail.account')}</dt>
                <dd className="font-medium break-all">{accountEmail}</dd>
            </div>

            <div className="grid gap-0.5">
                <dt className="text-muted-foreground">{t('integrations.detail.connectedOn')}</dt>
                <dd className="tabular-nums">
                    <time dateTime={connectedAt}>{connectedOn}</time>
                </dd>
            </div>
        </dl>
    );
}
