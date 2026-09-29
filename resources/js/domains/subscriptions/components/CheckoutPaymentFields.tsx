import {
    ExpressCheckoutElement,
    PaymentElement,
    type StripeCheckoutElementsValue,
} from '@stripe/react-stripe-js/checkout';
import type { StripeExpressCheckoutElementReadyEvent } from '@stripe/stripe-js';
import { CircleAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import { CheckoutTermsNotice } from './CheckoutTermsNotice';
import { useCheckoutPayment } from './use-checkout-payment';
import type { Subscription } from '../types';

const PAYMENT_ELEMENT_OPTIONS = { layout: 'tabs' } as const;

const ACTION_CLASS_NAME = 'w-full rounded-lg sm:w-auto';

type Props = {
    checkout: StripeCheckoutElementsValue;
    sessionId: string;
    onSubscribed: (subscription: Subscription) => void;
    onCancel: () => void;
};

type DividerProps = {
    label: string;
};

function PaymentMethodDivider({ label }: DividerProps) {
    return (
        <p className="flex items-center gap-3 text-xs text-muted-foreground">
            <span aria-hidden="true" className="h-px flex-1 bg-border" />
            {label}
            <span aria-hidden="true" className="h-px flex-1 bg-border" />
        </p>
    );
}

type DeclineProps = {
    message: string;
};

function PaymentDeclineMessage({ message }: DeclineProps) {
    return (
        <p
            role="alert"
            className="flex gap-2.5 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm leading-relaxed text-destructive"
        >
            <CircleAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
            {message}
        </p>
    );
}

export function CheckoutPaymentFields({ checkout, sessionId, onSubscribed, onCancel }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const payment = useCheckoutPayment({ checkout, sessionId, onSubscribed });
    const [offersWallets, setOffersWallets] = useState(false);

    function handleWalletsReady(event: StripeExpressCheckoutElementReadyEvent) {
        setOffersWallets(event.availablePaymentMethods !== undefined);
    }

    return (
        <form noValidate onSubmit={payment.submit} aria-busy={payment.isPaying} className="grid gap-6">
            <div className="grid gap-5">
                <ExpressCheckoutElement onReady={handleWalletsReady} onConfirm={payment.payWithWallet} />

                {offersWallets ? <PaymentMethodDivider label={t('plan.checkout.orPayWithCard')} /> : null}

                <PaymentElement options={PAYMENT_ELEMENT_OPTIONS} />
            </div>

            {payment.declineMessage !== null ? <PaymentDeclineMessage message={payment.declineMessage} /> : null}

            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <SubmitButton
                    variant="brand"
                    size="xl"
                    label={t('plan.checkout.subscribe')}
                    submittingLabel={t('plan.checkout.subscribing')}
                    isSubmitting={payment.isPaying}
                    className={ACTION_CLASS_NAME}
                />

                <Button
                    type="button"
                    variant="ghost"
                    size="xl"
                    disabled={payment.isPaying}
                    onClick={onCancel}
                    className={ACTION_CLASS_NAME}
                >
                    {tCommon('actions.cancel')}
                </Button>
            </div>

            <CheckoutTermsNotice />
        </form>
    );
}
