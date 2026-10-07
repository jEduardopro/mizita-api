import { useTranslation } from 'react-i18next';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { NOTIFICATION_SCOPES, type NotificationScope } from '../types';

const SCOPE_LABEL_KEYS = {
    mine: 'notifications.scope.mine',
    team: 'notifications.scope.team',
} as const satisfies Record<NotificationScope, string>;

function isNotificationScope(value: string): value is NotificationScope {
    return (NOTIFICATION_SCOPES as readonly string[]).includes(value);
}

type Props = {
    value: NotificationScope;
    onValueChange: (scope: NotificationScope) => void;
};

export function NotificationScopeFilter({ value, onValueChange }: Props) {
    const { t } = useTranslation('admin');

    return (
        <ToggleGroup
            type="single"
            variant="outline"
            spacing={0}
            value={value}
            onValueChange={(next) => {
                if (isNotificationScope(next)) {
                    onValueChange(next);
                }
            }}
            aria-label={t('notifications.scope.label')}
            className="w-full sm:w-auto"
        >
            {NOTIFICATION_SCOPES.map((scope) => (
                <ToggleGroupItem key={scope} value={scope} className="h-11 flex-1 px-4 sm:flex-none md:h-9">
                    {t(SCOPE_LABEL_KEYS[scope])}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
