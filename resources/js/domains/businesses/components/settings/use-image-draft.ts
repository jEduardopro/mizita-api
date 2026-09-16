import { useCallback, useState } from 'react';
import { useObjectUrl } from '@/hooks/use-object-url';

export type ImageDraft = {
    file: File | null;
    shownUrl: string | null;
    isCleared: boolean;
    select: (file: File) => void;
    clear: () => void;
};

export function useImageDraft(savedUrl: string | null): ImageDraft {
    const [file, setFile] = useState<File | null>(null);
    const [keptUrl, setKeptUrl] = useState(savedUrl);
    const [loadedUrl, setLoadedUrl] = useState(savedUrl);

    if (savedUrl !== loadedUrl) {
        setLoadedUrl(savedUrl);
        setKeptUrl(savedUrl);
        setFile(null);
    }

    const objectUrl = useObjectUrl(file);

    const clear = useCallback(() => {
        setFile(null);
        setKeptUrl(null);
    }, []);

    return {
        file,
        shownUrl: file === null ? keptUrl : objectUrl,
        isCleared: savedUrl !== null && keptUrl === null,
        select: setFile,
        clear,
    };
}
