import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { SwitchToFreeDialog } from './SwitchToFreeDialog';

type Props = {
    accessEndsOn: string | null;
    className?: string;
};

export function SwitchToFreeLauncher({ accessEndsOn, className }: Props) {
    const { t } = useTranslation('admin');
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button type="button" variant="outline" size="xl" onClick={() => setOpen(true)} className={className}>
                {t('plan.settings.switchToFree')}
            </Button>

            <SwitchToFreeDialog open={open} onOpenChange={setOpen} accessEndsOn={accessEndsOn} />
        </>
    );
}
