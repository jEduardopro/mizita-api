import type { BrandColor } from '@/domains/businesses/types';

type BrandColorClasses = {
    accent: string;
    accentForeground: string;
    surface: string;
};

export const brandColorClasses: Record<BrandColor, BrandColorClasses> = {
    ink: {
        accent: 'bg-brand-accent-ink',
        accentForeground: 'text-brand-accent-ink-foreground',
        surface: 'bg-brand-accent-ink-surface',
    },
    red: {
        accent: 'bg-brand-accent-red',
        accentForeground: 'text-brand-accent-red-foreground',
        surface: 'bg-brand-accent-red-surface',
    },
    orange: {
        accent: 'bg-brand-accent-orange',
        accentForeground: 'text-brand-accent-orange-foreground',
        surface: 'bg-brand-accent-orange-surface',
    },
    amber: {
        accent: 'bg-brand-accent-amber',
        accentForeground: 'text-brand-accent-amber-foreground',
        surface: 'bg-brand-accent-amber-surface',
    },
    purple: {
        accent: 'bg-brand-accent-purple',
        accentForeground: 'text-brand-accent-purple-foreground',
        surface: 'bg-brand-accent-purple-surface',
    },
    blue: {
        accent: 'bg-brand-accent-blue',
        accentForeground: 'text-brand-accent-blue-foreground',
        surface: 'bg-brand-accent-blue-surface',
    },
    sand: {
        accent: 'bg-brand-accent-sand',
        accentForeground: 'text-brand-accent-sand-foreground',
        surface: 'bg-brand-accent-sand-surface',
    },
    slate: {
        accent: 'bg-brand-accent-slate',
        accentForeground: 'text-brand-accent-slate-foreground',
        surface: 'bg-brand-accent-slate-surface',
    },
    teal: {
        accent: 'bg-brand-accent-teal',
        accentForeground: 'text-brand-accent-teal-foreground',
        surface: 'bg-brand-accent-teal-surface',
    },
    green: {
        accent: 'bg-brand-accent-green',
        accentForeground: 'text-brand-accent-green-foreground',
        surface: 'bg-brand-accent-green-surface',
    },
};
