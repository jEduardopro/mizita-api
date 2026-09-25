import { Mail } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { StaffRole } from '../types';
import { ROLE_LABEL_KEYS } from './profile-role';

type BadgeVariant = ComponentProps<typeof Badge>['variant'];

const LEVEL_VARIANTS = {
    owner: 'secondary',
    staff: 'outline',
    no_access: 'outline',
} as const satisfies Record<StaffRole, BadgeVariant>;

const LEVEL_CLASSES = {
    owner: undefined,
    staff: undefined,
    no_access: 'border-dashed text-muted-foreground',
} as const satisfies Record<StaffRole, string | undefined>;

type Props = {
    level: StaffRole;
    invitationPending: boolean;
};

export function TeamMemberBadges({ level, invitationPending }: Props) {
    const { t } = useTranslation('admin');

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <Badge variant={LEVEL_VARIANTS[level]} className={LEVEL_CLASSES[level]}>
                {t(ROLE_LABEL_KEYS[level])}
            </Badge>

            {invitationPending ? (
                <Badge variant="outline" className="border-warning/30 bg-warning-surface text-warning">
                    <Mail aria-hidden="true" />
                    {t('team.invitationPending')}
                </Badge>
            ) : null}
        </div>
    );
}
