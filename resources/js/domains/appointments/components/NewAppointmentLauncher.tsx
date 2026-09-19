import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useAuthorization } from '@/hooks/use-authorization';
import { NewAppointmentDialog } from './NewAppointmentDialog';

function browserTimezone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

type Props = {
    timezone?: string | null;
};

export function NewAppointmentLauncher({ timezone = null }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const [open, setOpen] = useState(false);

    if (! can('create_appointment')) {
        return null;
    }

    return (
        <>
            <Button
                type="button"
                variant="brand-outline"
                size="lg"
                onClick={() => setOpen(true)}
                className="h-11 rounded-full px-4 md:h-9 md:px-3.5"
            >
                <Plus aria-hidden="true" />
                {t('calendar.appointment.actions.launch')}
            </Button>

            <NewAppointmentDialog
                mode="create"
                open={open}
                onOpenChange={setOpen}
                appointment={null}
                timezone={timezone ?? browserTimezone()}
            />
        </>
    );
}
