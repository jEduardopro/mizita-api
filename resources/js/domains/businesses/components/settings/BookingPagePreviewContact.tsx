import { cn } from 'cn';
import { MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { LINK_PLATFORMS, type LinkPlatform } from '@/domains/businesses/types';

const PLATFORM_LABEL_KEYS = {
    website: 'businessSettings.preview.social.platforms.website',
    instagram: 'businessSettings.preview.social.platforms.instagram',
    facebook: 'businessSettings.preview.social.platforms.facebook',
    tiktok: 'businessSettings.preview.social.platforms.tiktok',
    x: 'businessSettings.preview.social.platforms.x',
    linkedin: 'businessSettings.preview.social.platforms.linkedin',
    youtube: 'businessSettings.preview.social.platforms.youtube',
    whatsapp: 'businessSettings.preview.social.platforms.whatsapp',
} as const satisfies Record<LinkPlatform, string>;

type Props = {
    street: string;
    city: string;
    postalCode: string;
    links: Partial<Record<LinkPlatform, string>>;
    chipClassName: string;
};

export function BookingPagePreviewContact({
    street,
    city,
    postalCode,
    links,
    chipClassName,
}: Props) {
    const { t } = useTranslation('admin');

    const locality = [postalCode.trim(), city.trim()].filter((part) => part !== '').join(' ');
    const addressLines = [street.trim(), locality].filter((line) => line !== '');
    const platforms = LINK_PLATFORMS.filter((platform) => (links[platform] ?? '').trim() !== '');

    return (
        <div className="grid gap-3">
            <div className="grid gap-1.5">
                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t('businessSettings.preview.locationTitle')}
                </h3>

                {addressLines.length === 0 ? (
                    <p className="text-xs text-muted-foreground">
                        {t('businessSettings.preview.locationEmpty')}
                    </p>
                ) : (
                    <p className="flex items-start gap-1.5 text-xs">
                        <MapPin
                            aria-hidden="true"
                            className="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                        />

                        <span className="grid">
                            {addressLines.map((line) => (
                                <span key={line}>{line}</span>
                            ))}
                        </span>
                    </p>
                )}
            </div>

            <div className="grid gap-1.5">
                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t('businessSettings.preview.social.title')}
                </h3>

                {platforms.length === 0 ? (
                    <p className="text-xs text-muted-foreground">
                        {t('businessSettings.preview.social.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-wrap gap-1.5">
                        {platforms.map((platform) => (
                            <li
                                key={platform}
                                className={cn(
                                    'flex h-7 items-center rounded-full px-2.5 text-xs font-medium',
                                    chipClassName,
                                )}
                            >
                                {t(PLATFORM_LABEL_KEYS[platform])}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
