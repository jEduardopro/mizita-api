export const BRAND_COLORS = [
    'ink',
    'red',
    'orange',
    'amber',
    'purple',
    'blue',
    'sand',
    'slate',
    'teal',
    'green',
] as const;

export type BrandColor = (typeof BRAND_COLORS)[number];

export const BUTTON_SHAPES = ['pill', 'rounded', 'rectangle'] as const;

export type ButtonShape = (typeof BUTTON_SHAPES)[number];

export const PAGE_THEMES = ['system', 'light', 'dark'] as const;

export type PageTheme = (typeof PAGE_THEMES)[number];

export const LINK_PLATFORMS = [
    'website',
    'instagram',
    'facebook',
    'tiktok',
    'x',
    'linkedin',
    'youtube',
    'whatsapp',
] as const;

export type LinkPlatform = (typeof LINK_PLATFORMS)[number];

export const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7] as const;

export type WeekdayNumber = (typeof WEEKDAYS)[number];

export type TimeInterval = {
    starts_at: string;
    ends_at: string;
};

export type WeeklyHours = Record<WeekdayNumber, TimeInterval[]>;

export type GalleryImage = {
    id: string;
    url: string;
};

export type BrandColorClasses = {
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

export const BUTTON_SHAPE_CLASSES: Record<ButtonShape, string> = {
    pill: 'rounded-full',
    rounded: 'rounded-lg',
    rectangle: 'rounded-none',
};

export const THEME_SCOPES: Record<PageTheme, string | undefined> = {
    system: undefined,
    light: 'theme-light',
    dark: 'dark',
};
