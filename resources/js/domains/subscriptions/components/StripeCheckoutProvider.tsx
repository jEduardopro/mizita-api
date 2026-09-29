import { CheckoutElementsProvider } from '@stripe/react-stripe-js/checkout';
import type { StripeCheckoutElementsSdkOptions } from '@stripe/stripe-js';
import { useState, type ReactNode } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { STRIPE_APPEARANCES } from './stripe-appearance';
import { stripeClient } from './stripe-client';

type Props = {
    publishableKey: string;
    clientSecret: string;
    children: ReactNode;
};

export function StripeCheckoutProvider({ publishableKey, clientSecret, children }: Props) {
    const { resolvedAppearance } = useAppearance();
    const [stripe] = useState(() => stripeClient(publishableKey));
    const [options] = useState<StripeCheckoutElementsSdkOptions>(() => ({
        clientSecret,
        elementsOptions: { appearance: STRIPE_APPEARANCES[resolvedAppearance] },
    }));

    return (
        <CheckoutElementsProvider stripe={stripe} options={options}>
            {children}
        </CheckoutElementsProvider>
    );
}
