import { Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';

export function PlanLockedBadge() {
    const { t } = useTranslation('admin');

    return (
        <Badge variant="outline" className="rounded-md font-normal text-muted-foreground">
            <Sparkles aria-hidden="true" data-icon="inline-start" />
            <span aria-hidden="true">{t('plan.names.complete')}</span>
            <span className="sr-only">{t('plan.notice.title')}</span>
        </Badge>
    );
}
