import { useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useErrorToast } from '@/hooks/use-error-toast';
import { formMessageFrom, isNotFoundError } from '@/lib/http';
import {
    useAccountReactivation,
    useDismissAccountReactivation,
    useReactivateAccount,
} from '../queries';
import type { AccountReactivationStatus } from '../types';

const LOGIN_URL = '/login';

const CALENDAR_URL = '/calendar';

const HOME_URL = '/';

const TWO_FACTOR_CHALLENGE_URL = '/two-factor-challenge';

export type ReactivationAction = 'reactivate' | 'dismiss';

type AccountReactivationFlow = {
    status: AccountReactivationStatus | undefined;
    hasFailed: boolean;
    isRetrying: boolean;
    retry: () => void;
    reactivate: () => void;
    dismiss: () => void;
    pendingAction: ReactivationAction | null;
};

export function useAccountReactivationFlow(): AccountReactivationFlow {
    const { t } = useTranslation('auth');
    const errorToast = useErrorToast();
    const reactivation = useAccountReactivation();
    const reactivateAccount = useReactivateAccount();
    const dismissReactivation = useDismissAccountReactivation();
    const { mutate: reactivateMutate } = reactivateAccount;
    const { mutate: dismissMutate } = dismissReactivation;

    const nothingPending = reactivation.isError && isNotFoundError(reactivation.error);

    useEffect(() => {
        if (nothingPending) {
            window.location.replace(LOGIN_URL);
        }
    }, [nothingPending]);

    const status = reactivation.data;
    const destination = status?.business ? CALENDAR_URL : HOME_URL;

    const reactivate = useCallback(() => {
        errorToast.dismiss();

        reactivateMutate(undefined, {
            onSuccess: ({ two_factor_required }) =>
                window.location.assign(two_factor_required ? TWO_FACTOR_CHALLENGE_URL : destination),
            onError: (error) => errorToast.show(formMessageFrom(error, t('reactivateAccount.unexpected'))),
        });
    }, [errorToast, reactivateMutate, destination, t]);

    const dismiss = useCallback(() => {
        errorToast.dismiss();

        dismissMutate(undefined, {
            onSuccess: () => window.location.replace(LOGIN_URL),
            onError: (error) => errorToast.show(formMessageFrom(error, t('reactivateAccount.dismissFailed'))),
        });
    }, [errorToast, dismissMutate, t]);

    const { refetch } = reactivation;

    const retry = useCallback(() => {
        void refetch();
    }, [refetch]);

    return {
        status,
        hasFailed: reactivation.isError && ! nothingPending,
        isRetrying: reactivation.isFetching,
        retry,
        reactivate,
        dismiss,
        pendingAction: pendingActionOf(
            reactivateAccount.isPending || reactivateAccount.isSuccess,
            dismissReactivation.isPending || dismissReactivation.isSuccess,
        ),
    };
}

function pendingActionOf(isReactivating: boolean, isDismissing: boolean): ReactivationAction | null {
    if (isReactivating) {
        return 'reactivate';
    }

    return isDismissing ? 'dismiss' : null;
}
