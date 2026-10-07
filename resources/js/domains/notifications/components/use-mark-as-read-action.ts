import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useErrorToast } from '@/hooks/use-error-toast';
import { formMessageFrom } from '@/lib/http';
import { useMarkNotificationAsRead } from '../queries';

type MarkAsReadAction = {
    markAsRead: (id: string) => void;
    pendingId: string | null;
};

export function useMarkAsReadAction(): MarkAsReadAction {
    const { t } = useTranslation('admin');
    const errorToast = useErrorToast();
    const { mutate, isPending, variables } = useMarkNotificationAsRead();

    const markAsRead = useCallback(
        (id: string) => {
            errorToast.dismiss();

            mutate(id, {
                onError: (error) =>
                    errorToast.show(formMessageFrom(error, t('notifications.markAsReadFailed'))),
            });
        },
        [errorToast, mutate, t],
    );

    return { markAsRead, pendingId: isPending ? (variables ?? null) : null };
}
