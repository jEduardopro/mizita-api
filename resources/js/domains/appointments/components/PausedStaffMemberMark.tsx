import { CirclePause } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';

export function PausedStaffMemberMark() {
    const { t } = useTranslation('admin');

    return (
        <Badge variant="outline" className="bg-muted text-muted-foreground">
            <CirclePause aria-hidden="true" />
            {t('plan.team.paused.badge')}
        </Badge>
    );
}
