import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast } from '@/lib/toast';
import { useStartGoogleCalendarAuthorization } from '../queries';

type Props = {
    label: string;
};

export function ConnectGoogleCalendarButton({ label }: Props) {
    const { t } = useTranslation('admin');
    const startAuthorization = useStartGoogleCalendarAuthorization();
    const { reset } = startAuthorization;
    const isLeavingForGoogle = startAuthorization.isPending || startAuthorization.isSuccess;

    useEffect(() => {
        function resetWhenRestoredFromHistory(event: PageTransitionEvent) {
            if (event.persisted) {
                reset();
            }
        }

        window.addEventListener('pageshow', resetWhenRestoredFromHistory);

        return () => window.removeEventListener('pageshow', resetWhenRestoredFromHistory);
    }, [reset]);

    async function connect() {
        try {
            const { authorization_url } = await startAuthorization.mutateAsync();

            window.location.assign(authorization_url);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('integrations.googleCalendar.connectFailed')));
        }
    }

    return (
        <SubmitButton
            type="button"
            variant="brand"
            label={label}
            submittingLabel={t('integrations.googleCalendar.connecting')}
            isSubmitting={isLeavingForGoogle}
            onClick={() => void connect()}
            className="h-11 w-full px-4 md:h-9"
        />
    );
}
