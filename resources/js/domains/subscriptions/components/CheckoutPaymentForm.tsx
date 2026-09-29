import { useCheckoutElements } from '@stripe/react-stripe-js/checkout';
import { useTranslation } from 'react-i18next';
import { CheckoutPaymentFields } from './CheckoutPaymentFields';
import { CheckoutPaymentSkeleton } from './CheckoutPaymentSkeleton';
import { SubscriptionFailure } from './SubscriptionFailure';
import type { Subscription } from '../types';

type Props = {
    sessionId: string;
    onSubscribed: (subscription: Subscription) => void;
    onCancel: () => void;
    onRetry: () => void;
};

export function CheckoutPaymentForm({ sessionId, onSubscribed, onCancel, onRetry }: Props) {
    const { t } = useTranslation('admin');
    const result = useCheckoutElements();

    if (result.type === 'loading') {
        return <CheckoutPaymentSkeleton />;
    }

    if (result.type === 'error') {
        return <SubscriptionFailure message={t('plan.checkout.errors.load')} onRetry={onRetry} />;
    }

    return (
        <CheckoutPaymentFields
            checkout={result.checkout}
            sessionId={sessionId}
            onSubscribed={onSubscribed}
            onCancel={onCancel}
        />
    );
}
