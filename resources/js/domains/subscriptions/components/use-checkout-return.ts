import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useErrorToast } from '@/hooks/use-error-toast';
import { formMessageFrom } from '@/lib/http';
import { raiseSuccessToast } from '@/lib/toast';
import { checkoutOutcomeKey } from './checkout-outcome';
import { useConfirmCheckout } from '../queries';

const SESSION_QUERY_PARAMETER = 'session_id';

function takeReturnedSessionId(): string | null {
    const url = new URL(window.location.href);
    const sessionId = url.searchParams.get(SESSION_QUERY_PARAMETER);

    if (sessionId === null) {
        return null;
    }

    url.searchParams.delete(SESSION_QUERY_PARAMETER);
    window.history.replaceState(window.history.state, '', url);

    return sessionId === '' ? null : sessionId;
}

export function useCheckoutReturn(): boolean {
    const { t } = useTranslation('admin');
    const { mutateAsync: confirmCheckout, isPending } = useConfirmCheckout();
    const errorToast = useErrorToast();
    const handled = useRef(false);

    useEffect(() => {
        if (handled.current) {
            return;
        }

        handled.current = true;

        const sessionId = takeReturnedSessionId();

        if (sessionId === null) {
            return;
        }

        confirmCheckout(sessionId).then(
            (subscription) => raiseSuccessToast(t(checkoutOutcomeKey(subscription))),
            (error: unknown) => errorToast.show(formMessageFrom(error, t('plan.checkout.errors.confirm'))),
        );
    }, [confirmCheckout, errorToast, t]);

    return isPending;
}
