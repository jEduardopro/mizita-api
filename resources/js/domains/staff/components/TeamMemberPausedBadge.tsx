import { CirclePause } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

export function TeamMemberPausedBadge() {
    const { t } = useTranslation('admin');

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="group relative -mx-1 -my-3 inline-flex items-center rounded-full px-1 py-3 outline-none"
                >
                    <Badge
                        variant="outline"
                        className="bg-muted text-muted-foreground group-focus-visible:border-ring group-focus-visible:ring-[3px] group-focus-visible:ring-ring/50 group-data-[state=open]:border-ring"
                    >
                        <CirclePause aria-hidden="true" />
                        {t('plan.team.paused.badge')}
                    </Badge>
                </button>
            </PopoverTrigger>

            <PopoverContent align="start" className="w-64 text-sm text-pretty">
                {t('plan.team.paused.tooltip')}
            </PopoverContent>
        </Popover>
    );
}
