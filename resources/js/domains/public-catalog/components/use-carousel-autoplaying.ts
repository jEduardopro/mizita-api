import { useCallback, useSyncExternalStore } from 'react';
import { useCarousel } from '@/components/ui/carousel';

const NO_SUBSCRIPTION = () => {};

export function useCarouselAutoplaying(): boolean {
    const { api } = useCarousel();

    const subscribe = useCallback(
        (onChange: () => void) => {
            if (api === undefined) {
                return NO_SUBSCRIPTION;
            }

            api.on('autoplay:play', onChange);
            api.on('autoplay:stop', onChange);
            api.on('reInit', onChange);

            return () => {
                api.off('autoplay:play', onChange);
                api.off('autoplay:stop', onChange);
                api.off('reInit', onChange);
            };
        },
        [api],
    );

    return useSyncExternalStore(
        subscribe,
        () => api?.plugins().autoplay?.isPlaying() ?? false,
    );
}
