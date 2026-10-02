import { useTranslation } from 'react-i18next';
import type { PlatformBusinessOwner as Owner } from '../types';

type Props = {
    owner: Owner | null;
};

export function PlatformBusinessOwner({ owner }: Props) {
    const { t } = useTranslation('platform');

    if (owner === null) {
        return <span className="text-sm text-muted-foreground">{t('businesses.noOwner')}</span>;
    }

    return (
        <span className="grid min-w-0 leading-tight">
            <span className="truncate text-sm">{owner.name}</span>
            <span className="truncate text-xs text-muted-foreground">{owner.email}</span>
        </span>
    );
}
