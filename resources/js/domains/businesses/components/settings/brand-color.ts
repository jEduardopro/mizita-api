import type { BrandColor } from '@/domains/businesses/types';

type BrandColorClasses = {
    accent: string;
    surface: string;
};

export const brandColorClasses: Record<BrandColor, BrandColorClasses> = {
    ink: {
        accent: 'bg-brand-accent-ink',
        surface: 'bg-brand-accent-ink-surface',
    },
    red: {
        accent: 'bg-brand-accent-red',
        surface: 'bg-brand-accent-red-surface',
    },
    orange: {
        accent: 'bg-brand-accent-orange',
        surface: 'bg-brand-accent-orange-surface',
    },
    amber: {
        accent: 'bg-brand-accent-amber',
        surface: 'bg-brand-accent-amber-surface',
    },
    purple: {
        accent: 'bg-brand-accent-purple',
        surface: 'bg-brand-accent-purple-surface',
    },
    blue: {
        accent: 'bg-brand-accent-blue',
        surface: 'bg-brand-accent-blue-surface',
    },
    sand: {
        accent: 'bg-brand-accent-sand',
        surface: 'bg-brand-accent-sand-surface',
    },
    slate: {
        accent: 'bg-brand-accent-slate',
        surface: 'bg-brand-accent-slate-surface',
    },
    teal: {
        accent: 'bg-brand-accent-teal',
        surface: 'bg-brand-accent-teal-surface',
    },
    green: {
        accent: 'bg-brand-accent-green',
        surface: 'bg-brand-accent-green-surface',
    },
};
