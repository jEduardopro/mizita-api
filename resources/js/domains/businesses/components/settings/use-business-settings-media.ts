import {
    useAttachBookingPageBanner,
    useAttachBusinessLogo,
    useRemoveBookingPageBanner,
    useRemoveBusinessLogo,
} from '@/domains/businesses/queries';
import type { BusinessSettings, GalleryImage } from '@/domains/businesses/types';
import { useGalleryDraft, type GalleryDraft } from './use-gallery-draft';
import { useImageDraft, type ImageDraft } from './use-image-draft';

const NO_GALLERY: GalleryImage[] = [];

export type BusinessSettingsMedia = {
    logo: ImageDraft;
    banner: ImageDraft;
    gallery: GalleryDraft;
    isSyncing: boolean;
    sync: () => Promise<boolean>;
};

export function useBusinessSettingsMedia(settings: BusinessSettings | null): BusinessSettingsMedia {
    const logo = useImageDraft(settings?.logo_url ?? null);
    const banner = useImageDraft(settings?.booking_page.banner_url ?? null);
    const gallery = useGalleryDraft(settings?.booking_page.gallery ?? NO_GALLERY);

    const attachLogo = useAttachBusinessLogo();
    const removeLogo = useRemoveBusinessLogo();
    const attachBanner = useAttachBookingPageBanner();
    const removeBanner = useRemoveBookingPageBanner();

    async function syncLogo() {
        if (logo.isCleared) {
            await removeLogo.mutateAsync();
        }

        if (logo.file !== null) {
            await attachLogo.mutateAsync(logo.file);
        }
    }

    async function syncBanner() {
        if (banner.isCleared) {
            await removeBanner.mutateAsync();
        }

        if (banner.file !== null) {
            await attachBanner.mutateAsync(banner.file);
        }
    }

    async function sync(): Promise<boolean> {
        try {
            await syncLogo();
            await syncBanner();
            await gallery.sync();

            return true;
        } catch {
            return false;
        }
    }

    return {
        logo,
        banner,
        gallery,
        isSyncing:
            attachLogo.isPending ||
            removeLogo.isPending ||
            attachBanner.isPending ||
            removeBanner.isPending ||
            gallery.isSyncing,
        sync,
    };
}
