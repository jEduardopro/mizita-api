import { useTranslation } from 'react-i18next';
import { formMessageFrom } from '@/lib/http';
import { CheckoutPaymentForm } from './CheckoutPaymentForm';
import { CheckoutPaymentSkeleton } from './CheckoutPaymentSkeleton';
import { stripePublishableKey } from './stripe-client';
import { StripeCheckoutProvider } from './StripeCheckoutProvider';
import { SubscriptionFailure } from './SubscriptionFailure';
import type { CheckoutSession, Subscription } from '../types';

type Props = {
    session: CheckoutSession | undefined;
    startError: Error | null;
    onRetry: () => void;
    onSubscribed: (subscription: Subscription) => void;
    onCancel: () => void;
};

export function CheckoutPaymentPanel({ session, startError, onRetry, onSubscribed, onCancel }: Props) {
    const { t } = useTranslation('admin');
    const publishableKey = stripePublishableKey();

    if (publishableKey === null) {
        return <SubscriptionFailure message={t('plan.checkout.errors.unavailable')} />;
    }

    if (startError !== null) {
        return (
            <SubscriptionFailure
                message={formMessageFrom(startError, t('plan.checkout.errors.start'))}
                onRetry={onRetry}
            />
        );
    }

    if (session === undefined) {
        return <CheckoutPaymentSkeleton />;
    }

    return (
        <StripeCheckoutProvider
            key={session.session_id}
            publishableKey={publishableKey}
            clientSecret={session.client_secret}
        >
            <CheckoutPaymentForm
                sessionId={session.session_id}
                onSubscribed={onSubscribed}
                onCancel={onCancel}
                onRetry={onRetry}
            />
        </StripeCheckoutProvider>
    );
}
