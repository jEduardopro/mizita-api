import { Phone } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { PublicLink } from '../types';
import { PILL_CLASSES, PLATFORM_ICONS, PLATFORM_LABEL_KEYS } from './booking-links';

type Props = {
    phone: string | null;
    website: PublicLink | null;
};

export function BookingContactPills({ phone, website }: Props) {
    const { t } = useTranslation('public');

    if (phone === null && website === null) {
        return null;
    }

    const WebsiteIcon = PLATFORM_ICONS.website;

    return (
        <ul className="flex flex-wrap justify-center gap-2">
            {phone === null ? null : (
                <li>
                    <a href={`tel:${phone}`} className={PILL_CLASSES}>
                        <Phone
                            aria-hidden="true"
                            className="size-4 shrink-0 text-muted-foreground"
                        />
                        {phone}
                    </a>
                </li>
            )}

            {website === null ? null : (
                <li>
                    <a
                        href={website.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={PILL_CLASSES}
                    >
                        <WebsiteIcon
                            aria-hidden="true"
                            className="size-4 shrink-0 text-muted-foreground"
                        />
                        {t(PLATFORM_LABEL_KEYS.website)}
                    </a>
                </li>
            )}
        </ul>
    );
}
