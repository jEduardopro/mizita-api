import { cn } from 'cn';
import type { BrandColorClasses, GalleryImage } from '@/lib/booking-brand';
import { BookingGalleryViewer } from './BookingGalleryViewer';
import { BookingHeroCarousel } from './BookingHeroCarousel';
import { BookingHeroFrame } from './BookingHeroFrame';
import { useGalleryViewer } from './use-gallery-viewer';

const BANNER_SLIDE_ID = 'banner';

const PHONE_PHOTO_HEIGHT = 'h-[clamp(150px,24svh,240px)]';
const WIDE_PHOTO_HEIGHT = 'sm:h-[clamp(160px,34svh,340px)]';
const ACCENT_HEIGHT = 'h-[clamp(100px,14svh,150px)] sm:h-[clamp(110px,18svh,190px)]';

type Props = {
    bannerUrl: string | null;
    gallery: GalleryImage[];
    businessName: string;
    themeScope: string | undefined;
    accent: BrandColorClasses;
};

function heroSlidesFrom(bannerUrl: string | null, gallery: GalleryImage[]): GalleryImage[] {
    if (bannerUrl === null) {
        return gallery;
    }

    return [{ id: BANNER_SLIDE_ID, url: bannerUrl }, ...gallery];
}

export function BookingHero({ bannerUrl, gallery, businessName, themeScope, accent }: Props) {
    const { openAt, viewerState } = useGalleryViewer();

    const slides = heroSlidesFrom(bannerUrl, gallery);

    if (slides.length === 0) {
        return (
            <div className="mx-auto w-full max-w-5xl sm:px-8">
                <BookingHeroFrame surfaceClassName={accent.surface} className={ACCENT_HEIGHT} />
            </div>
        );
    }

    return (
        <div className="mx-auto w-full max-w-5xl sm:px-8">
            <BookingHeroFrame
                surfaceClassName={accent.surface}
                className={cn(PHONE_PHOTO_HEIGHT, 'sm:hidden')}
            >
                <BookingHeroCarousel
                    slides={slides}
                    businessName={businessName}
                    onOpenSlide={openAt}
                />
            </BookingHeroFrame>

            <BookingHeroFrame
                surfaceClassName={accent.surface}
                className={cn('hidden sm:block', bannerUrl === null ? ACCENT_HEIGHT : WIDE_PHOTO_HEIGHT)}
            >
                {bannerUrl === null ? null : (
                    <img
                        src={bannerUrl}
                        alt={businessName}
                        fetchPriority="high"
                        className="size-full object-cover"
                    />
                )}
            </BookingHeroFrame>

            <BookingGalleryViewer
                images={slides}
                businessName={businessName}
                themeScope={themeScope}
                {...viewerState}
            />
        </div>
    );
}
