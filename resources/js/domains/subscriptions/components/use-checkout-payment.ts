import type { StripeCheckoutElementsValue } from '@stripe/react-stripe-js/checkout';
import type { StripeExpressCheckoutElementConfirmEvent } from '@stripe/stripe-js';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useErrorToast } from '@/hooks/use-error-toast';
import { formMessageFrom } from '@/lib/http';
import { CheckoutPaymentDeclined, usePayCheckout } from '../queries';
import type { Subscription } from '../types';

const REDIRECT_ONLY_WHEN_REQUIRED = 'if_required';

type Options = {
    checkout: StripeCheckoutElementsValue;
    sessionId: string;
    onSubscribed: (subscription: Subscription) => void;
};

export type CheckoutPayment = {
    declineMessage: string | null;
    isPaying: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
    payWithWallet: (event: StripeExpressCheckoutElementConfirmEvent) => void;
};

export function useCheckoutPayment({ checkout, sessionId, onSubscribed }: Options): CheckoutPayment {
    const { t } = useTranslation('admin');
    const pay = usePayCheckout();
    const errorToast = useErrorToast();

    const declineMessage = pay.error instanceof CheckoutPaymentDeclined ? pay.error.message : null;

    async function payWith(expressCheckoutConfirmEvent?: StripeExpressCheckoutElementConfirmEvent) {
        errorToast.dismiss();

        try {
            const subscription = await pay.mutateAsync({
                sessionId,
                confirmPayment: async () => {
                    const result = await checkout.confirm({
                        redirect: REDIRECT_ONLY_WHEN_REQUIRED,
                        expressCheckoutConfirmEvent,
                    });

                    if (result.type === 'error') {
                        throw new CheckoutPaymentDeclined(result.error.message);
                    }
                },
            });

            onSubscribed(subscription);
        } catch (error) {
            if (! (error instanceof CheckoutPaymentDeclined)) {
                errorToast.show(formMessageFrom(error, t('plan.checkout.errors.confirm')));
            }
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        void payWith();
    }

    function payWithWallet(event: StripeExpressCheckoutElementConfirmEvent) {
        void payWith(event);
    }

    return {
        declineMessage,
        isPaying: pay.isPending,
        submit,
        payWithWallet,
    };
}
