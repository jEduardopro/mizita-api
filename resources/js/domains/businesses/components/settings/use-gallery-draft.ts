import { useCallback, useEffect, useRef, useState } from 'react';
import {
    useAddGalleryImage,
    useRemoveGalleryImage,
    useReorderGallery,
} from '@/domains/businesses/queries';
import type { GalleryImage } from '@/domains/businesses/types';

export type GalleryDraft = {
    images: GalleryImage[];
    add: (files: File[]) => void;
    remove: (id: string) => void;
    reorder: (ids: string[]) => void;
    isSyncing: boolean;
    sync: () => Promise<void>;
};

type DraftState = {
    images: GalleryImage[];
    files: Map<string, File>;
};

function signatureOf(images: GalleryImage[]): string {
    return images.map((image) => image.id).join(',');
}

export function useGalleryDraft(savedImages: GalleryImage[]): GalleryDraft {
    const addImage = useAddGalleryImage();
    const removeImage = useRemoveGalleryImage();
    const reorderImages = useReorderGallery();

    const signature = signatureOf(savedImages);
    const [loadedSignature, setLoadedSignature] = useState(signature);
    const [draft, setDraft] = useState<DraftState>(() => ({
        images: savedImages,
        files: new Map(),
    }));
    const previewUrls = useRef<string[]>([]);

    useEffect(
        () => () => previewUrls.current.forEach((url) => URL.revokeObjectURL(url)),
        [],
    );

    if (signature !== loadedSignature) {
        setLoadedSignature(signature);
        setDraft({ images: savedImages, files: new Map() });
    }

    const add = useCallback((files: File[]) => {
        const added = files.map((file) => ({
            id: crypto.randomUUID(),
            url: URL.createObjectURL(file),
            file,
        }));

        previewUrls.current.push(...added.map((entry) => entry.url));

        setDraft((current) => ({
            images: [...current.images, ...added.map(({ id, url }) => ({ id, url }))],
            files: new Map([...current.files, ...added.map(({ id, file }) => [id, file] as const)]),
        }));
    }, []);

    const remove = useCallback((id: string) => {
        setDraft((current) => ({
            images: current.images.filter((image) => image.id !== id),
            files: current.files,
        }));
    }, []);

    const reorder = useCallback((ids: string[]) => {
        setDraft((current) => ({
            images: ids
                .map((id) => current.images.find((image) => image.id === id))
                .filter((image): image is GalleryImage => image !== undefined),
            files: current.files,
        }));
    }, []);

    async function persistAddedImages(draftIds: string[], survivingIds: string[]) {
        const knownIds = new Set(survivingIds);
        const persistedIds: string[] = [];

        for (const id of draftIds) {
            const file = draft.files.get(id);

            if (file === undefined) {
                persistedIds.push(id);

                continue;
            }

            const page = await addImage.mutateAsync(file);
            const created = page.gallery.find((image) => ! knownIds.has(image.id));

            if (created === undefined) {
                continue;
            }

            knownIds.add(created.id);
            persistedIds.push(created.id);
        }

        return persistedIds;
    }

    async function sync() {
        const savedIds = savedImages.map((image) => image.id);
        const draftIds = draft.images.map((image) => image.id);
        const removedIds = savedIds.filter((id) => ! draftIds.includes(id));
        const survivingIds = savedIds.filter((id) => draftIds.includes(id));
        const isUnchanged =
            removedIds.length === 0 &&
            ! draftIds.some((id) => draft.files.has(id)) &&
            draftIds.every((id, index) => savedIds[index] === id);

        if (isUnchanged) {
            return;
        }

        for (const id of removedIds) {
            await removeImage.mutateAsync(id);
        }

        await reorderImages.mutateAsync(await persistAddedImages(draftIds, survivingIds));
    }

    return {
        images: draft.images,
        add,
        remove,
        reorder,
        isSyncing: addImage.isPending || removeImage.isPending || reorderImages.isPending,
        sync,
    };
}
