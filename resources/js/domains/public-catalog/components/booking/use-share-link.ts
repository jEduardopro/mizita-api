import { useCallback } from 'react';
import { useCopyToClipboard } from '@/hooks/use-copy-to-clipboard';

type ShareLinkMessages = {
    title: string;
    copied: string;
    failed: string;
};

function isShareCancelled(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'AbortError';
}

export function useShareLink({ title, copied, failed }: ShareLinkMessages): (url: string) => void {
    const copy = useCopyToClipboard({ copied, failed });

    return useCallback(
        (url: string) => {
            if (typeof navigator.share !== 'function') {
                copy(url);

                return;
            }

            navigator.share({ title, url }).catch((error: unknown) => {
                if (! isShareCancelled(error)) {
                    copy(url);
                }
            });
        },
        [copy, title],
    );
}
