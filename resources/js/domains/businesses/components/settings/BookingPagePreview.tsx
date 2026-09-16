import { cn } from 'cn';
import { ImageOff, Store } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type {
    BrandColor,
    ButtonShape,
    LinkPlatform,
    PageTheme,
    WeeklyHours,
} from '@/domains/businesses/types';
import { BookingPagePreviewContact } from './BookingPagePreviewContact';
import { BookingPagePreviewHours } from './BookingPagePreviewHours';
import { brandColorClasses } from './brand-color';

const TITLE_ID = 'booking-page-preview-title';

const BUTTON_SHAPE_CLASSES: Record<ButtonShape, string> = {
    pill: 'rounded-full',
    rounded: 'rounded-lg',
    rectangle: 'rounded-none',
};

const THEME_SCOPES: Record<PageTheme, string | undefined> = {
    system: undefined,
    light: 'booking-preview-light',
    dark: 'dark',
};

export type BookingPagePreviewValues = {
    name: string;
    about: string;
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    theme: PageTheme;
    hours: WeeklyHours;
    timezone: string;
    street: string;
    city: string;
    postalCode: string;
    links: Partial<Record<LinkPlatform, string>>;
};

type Props = {
    values: BookingPagePreviewValues;
    logoUrl: string | null;
    bannerUrl: string | null;
};

export function BookingPagePreview({ values, logoUrl, bannerUrl }: Props) {
    const { t } = useTranslation('admin');

    const accent = brandColorClasses[values.accentColor];
    const name = values.name.trim();
    const about = values.about.trim();

    return (
        <section aria-labelledby={TITLE_ID} className="grid gap-3">
            <div className="grid gap-1">
                <h2 id={TITLE_ID} className="text-sm leading-none font-medium">
                    {t('businessSettings.preview.title')}
                </h2>

                <p className="text-xs text-muted-foreground">
                    {t('businessSettings.preview.description')}
                </p>
            </div>

            <div
                className={cn(
                    'overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm',
                    THEME_SCOPES[values.theme],
                )}
            >
                <div className={cn('relative aspect-video', accent.surface)}>
                    {bannerUrl === null ? (
                        <span className="absolute inset-0 grid content-center justify-items-center gap-1.5 px-4 text-center text-xs text-balance text-muted-foreground">
                            <ImageOff aria-hidden="true" className="size-5" />
                            {t('businessSettings.preview.bannerEmpty')}
                        </span>
                    ) : (
                        <img
                            src={bannerUrl}
                            alt=""
                            className="absolute inset-0 size-full object-cover"
                        />
                    )}
                </div>

                <div className="grid justify-items-center gap-3 px-4 pb-4">
                    <span
                        className={cn(
                            'relative z-10 -mt-9 grid size-18 place-content-center overflow-hidden rounded-full ring-4 ring-card',
                            accent.surface,
                        )}
                    >
                        {logoUrl === null ? (
                            <Store aria-hidden="true" className="size-6 text-muted-foreground" />
                        ) : (
                            <img src={logoUrl} alt="" className="size-full object-cover" />
                        )}
                    </span>

                    <p
                        className={cn(
                            'text-center text-base font-semibold text-balance',
                            name === '' && 'text-muted-foreground',
                        )}
                    >
                        {name === '' ? t('businessSettings.preview.namePlaceholder') : name}
                    </p>

                    <p className="line-clamp-3 text-center text-xs text-pretty text-muted-foreground">
                        {about === '' ? t('businessSettings.preview.aboutPlaceholder') : about}
                    </p>

                    <span
                        className={cn(
                            'flex h-11 w-full items-center justify-center px-4 text-sm font-medium',
                            accent.accent,
                            accent.accentForeground,
                            BUTTON_SHAPE_CLASSES[values.buttonShape],
                        )}
                    >
                        {t('businessSettings.preview.book')}
                    </span>
                </div>

                <div className="grid gap-4 border-t border-border px-4 py-4">
                    <BookingPagePreviewHours
                        hours={values.hours}
                        timezone={values.timezone}
                        todayClassName={accent.surface}
                    />

                    <BookingPagePreviewContact
                        street={values.street}
                        city={values.city}
                        postalCode={values.postalCode}
                        links={values.links}
                        chipClassName={accent.surface}
                    />
                </div>
            </div>
        </section>
    );
}
