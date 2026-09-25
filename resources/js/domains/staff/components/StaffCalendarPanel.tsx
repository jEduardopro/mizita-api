import type { ComponentProps } from 'react';
import { useTranslation } from 'react-i18next';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import { StaffCalendarPicker } from './StaffCalendarPicker';

type Props = ComponentProps<typeof StaffCalendarPicker> & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function StaffCalendarPanel({ open, onOpenChange, onSelect, ...pickerProps }: Props) {
    const { t } = useTranslation('admin');
    const isDesktop = useIsDesktop();
    const title = t('calendar.staffPicker.toggle');

    if (isDesktop) {
        if (! open) {
            return null;
        }

        return (
            <aside aria-label={title} className="w-64 shrink-0 overflow-y-auto overscroll-contain border-r border-border">
                <StaffCalendarPicker {...pickerProps} onSelect={onSelect} />
            </aside>
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="left" aria-describedby={undefined} className="gap-0">
                <SheetHeader className="pr-14">
                    <SheetTitle>{title}</SheetTitle>
                </SheetHeader>

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain pb-[env(safe-area-inset-bottom)]">
                    <StaffCalendarPicker
                        {...pickerProps}
                        onSelect={(staffMemberId) => {
                            onSelect(staffMemberId);
                            onOpenChange(false);
                        }}
                    />
                </div>
            </SheetContent>
        </Sheet>
    );
}
