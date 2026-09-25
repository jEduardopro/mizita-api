import { PanelLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

type Props = {
    selectedStaffMemberName: string;
    isOwnCalendarSelected: boolean;
    expanded: boolean;
    onToggle: () => void;
};

export function StaffCalendarToggle({ selectedStaffMemberName, isOwnCalendarSelected, expanded, onToggle }: Props) {
    const { t } = useTranslation('admin');
    const label = isOwnCalendarSelected ? t('calendar.staffPicker.yourCalendar') : selectedStaffMemberName;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    aria-expanded={expanded}
                    onClick={onToggle}
                    className="h-11 max-w-full min-w-0 px-3 md:h-9"
                >
                    <PanelLeft aria-hidden="true" />

                    <span className="min-w-0 truncate">{label}</span>
                </Button>
            </TooltipTrigger>

            <TooltipContent>{t('calendar.staffPicker.toggle')}</TooltipContent>
        </Tooltip>
    );
}
