import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import type { GalleryImage } from '@/domains/businesses/types';
import { GalleryGrid } from './GalleryGrid';
import { GallerySheet } from './GallerySheet';
import {
    GALLERY_IMAGE_MAXIMUM_BYTES,
    GALLERY_MAXIMUM_IMAGES,
    IMAGE_MIME_TYPES,
} from './settings-media';

const FIELD_ID = 'booking-page-gallery';

const ACCEPTED_MIME_TYPES: readonly string[] = IMAGE_MIME_TYPES;

function idsWithMove(images: GalleryImage[], id: string, to: number): string[] {
    const ids = images.map((image) => image.id);
    const from = ids.indexOf(id);

    if (from < 0 || to < 0 || to >= ids.length || to === from) {
        return ids;
    }

    ids.splice(from, 1);
    ids.splice(to, 0, id);

    return ids;
}

type Props = {
    images: GalleryImage[];
    onAdd: (files: File[]) => void;
    onRemove: (id: string) => void;
    onReorder: (ids: string[]) => void;
    error?: string;
};

export function GalleryField({ images, onAdd, onRemove, onReorder, error }: Props) {
    const { t } = useTranslation('admin');
    const [rejection, setRejection] = useState<string | null>(null);

    const remaining = GALLERY_MAXIMUM_IMAGES - images.length;
    const message = fieldMessage({
        id: FIELD_ID,
        error: error ?? rejection ?? undefined,
        hint: t('businessSettings.gallery.count', {
            used: images.length,
            max: GALLERY_MAXIMUM_IMAGES,
        }),
    });

    function selectFiles(chosen: File[]): void {
        if (chosen.length === 0) {
            return;
        }

        if (chosen.some((file) => ! ACCEPTED_MIME_TYPES.includes(file.type))) {
            setRejection(t('businessSettings.media.unsupported'));

            return;
        }

        if (chosen.some((file) => file.size > GALLERY_IMAGE_MAXIMUM_BYTES)) {
            setRejection(t('businessSettings.gallery.tooLarge'));

            return;
        }

        if (chosen.length > remaining) {
            setRejection(t('businessSettings.gallery.full', { max: GALLERY_MAXIMUM_IMAGES }));

            return;
        }

        setRejection(null);
        onAdd(chosen);
    }

    function moveTo(id: string, position: number): void {
        onReorder(idsWithMove(images, id, position));
    }

    return (
        <div className="grid gap-2">
            <span id={`${FIELD_ID}-label`} className="text-sm leading-none font-medium">
                {t('businessSettings.gallery.label')}
            </span>

            <div
                role="group"
                aria-labelledby={`${FIELD_ID}-label`}
                aria-describedby={message?.id}
                className="grid gap-3"
            >
                <GalleryGrid
                    images={images}
                    canAdd={remaining > 0}
                    onSelectFiles={selectFiles}
                    onRemove={onRemove}
                    onMoveTo={moveTo}
                />

                {images.length > 0 ? (
                    <GallerySheet
                        images={images}
                        onMoveUp={(id) => moveTo(id, images.findIndex((image) => image.id === id) - 1)}
                        onMoveDown={(id) => moveTo(id, images.findIndex((image) => image.id === id) + 1)}
                        onRemove={onRemove}
                    />
                ) : null}
            </div>

            <FieldMessage message={message} />
        </div>
    );
}
