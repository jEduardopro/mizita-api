import { useCallback, useSyncExternalStore } from 'react';
import { useCarousel } from '@/components/ui/carousel';

const NO_SUBSCRIPTION = () => {};

export function useCarouselPosition(): number {
    const { api, opts } = useCarousel();
    const initialIndex = opts?.startIndex ?? 0;

    const subscribe = useCallback(
        (onChange: () => void) => {
            if (api === undefined) {
                return NO_SUBSCRIPTION;
            }

            api.on('select', onChange);
            api.on('reInit', onChange);

            return () => {
                api.off('select', onChange);
                api.off('reInit', onChange);
            };
        },
        [api],
    );

    const selectedIndex = useSyncExternalStore(
        subscribe,
        () => api?.selectedScrollSnap() ?? initialIndex,
    );

    return selectedIndex + 1;
}
