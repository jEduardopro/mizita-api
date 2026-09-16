import { useEffect, useState } from 'react';

export function useObjectUrl(file: File | null): string | null {
    const [objectUrl, setObjectUrl] = useState<string | null>(null);

    useEffect(() => {
        if (file === null) {
            setObjectUrl(null);

            return;
        }

        const created = URL.createObjectURL(file);

        setObjectUrl(created);

        return () => URL.revokeObjectURL(created);
    }, [file]);

    return objectUrl;
}
