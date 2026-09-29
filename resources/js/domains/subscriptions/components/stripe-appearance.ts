import type { Appearance } from '@stripe/stripe-js';
import type { ResolvedAppearance } from '@/lib/appearance';

const SHARED_VARIABLES = {
    fontFamily: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
    fontSizeBase: '16px',
    borderRadius: '10px',
    spacingUnit: '4px',
} as const;

export const STRIPE_APPEARANCES = {
    light: {
        theme: 'stripe',
        variables: {
            ...SHARED_VARIABLES,
            colorPrimary: '#0168e0',
            colorBackground: '#ffffff',
            colorText: '#0a0d12',
            colorDanger: '#e7000b',
        },
    },
    dark: {
        theme: 'night',
        variables: {
            ...SHARED_VARIABLES,
            colorPrimary: '#6aa6ff',
            colorBackground: '#15181d',
            colorText: '#f5f7f9',
            colorDanger: '#ff6467',
        },
    },
} as const satisfies Record<ResolvedAppearance, Appearance>;
