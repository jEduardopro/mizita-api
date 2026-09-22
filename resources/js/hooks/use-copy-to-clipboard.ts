import { useCallback } from 'react';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';

type Messages = {
    copied: string;
    failed: string;
};

async function copyToClipboard(text: string, { copied, failed }: Messages): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
        raiseSuccessToast(copied);
    } catch {
        raiseErrorToast(failed);
    }
}

export function useCopyToClipboard({ copied, failed }: Messages): (text: string) => void {
    return useCallback(
        (text: string) => void copyToClipboard(text, { copied, failed }),
        [copied, failed],
    );
}
