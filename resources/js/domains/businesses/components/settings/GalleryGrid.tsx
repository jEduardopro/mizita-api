import { cn } from 'cn';
import { Plus, X } from 'lucide-react';
import { useId, useState, type DragEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { GalleryImage } from '@/domains/businesses/types';
import { IMAGE_MIME_TYPES } from './settings-media';

type Props = {
    images: GalleryImage[];
    canAdd: boolean;
    onSelectFiles: (files: File[]) => void;
    onRemove: (id: string) => void;
    onMoveTo: (id: string, position: number) => void;
};

export function GalleryGrid({ images, canAdd, onSelectFiles, onRemove, onMoveTo }: Props) {
    const { t } = useTranslation('admin');
    const inputId = useId();
    const [draggedId, setDraggedId] = useState<string | null>(null);

    function dropOn(event: DragEvent<HTMLDivElement>, position: number): void {
        event.preventDefault();

        if (draggedId === null) {
            return;
        }

        onMoveTo(draggedId, position);
        setDraggedId(null);
    }

    return (
        <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
            {canAdd ? (
                <div className="contents">
                    <input
                        id={inputId}
                        type="file"
                        multiple
                        accept={IMAGE_MIME_TYPES.join(',')}
                        onChange={(event) => onSelectFiles(Array.from(event.target.files ?? []))}
                        className="peer sr-only"
                    />

                    <label
                        htmlFor={inputId}
                        className="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-input bg-muted/40 px-2 text-center text-xs text-muted-foreground transition-colors hover:border-ring peer-focus-visible:border-ring peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50"
                    >
                        <Plus aria-hidden="true" className="size-5" />
                        {t('businessSettings.gallery.add')}
                    </label>
                </div>
            ) : null}

            {images.map((image, index) => (
                <div
                    key={image.id}
                    draggable
                    onDragStart={() => setDraggedId(image.id)}
                    onDragEnd={() => setDraggedId(null)}
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={(event) => dropOn(event, index)}
                    className={cn(
                        'relative aspect-square overflow-hidden rounded-xl border border-border',
                        draggedId === image.id ? 'opacity-50' : undefined,
                    )}
                >
                    <img src={image.url} alt="" className="size-full object-cover" />

                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => onRemove(image.id)}
                        aria-label={t('businessSettings.gallery.remove', { position: index + 1 })}
                        className="absolute top-1 right-1 size-8 rounded-full bg-background/85 p-0 backdrop-blur-sm after:absolute after:-inset-1.5 hover:bg-background"
                    >
                        <X aria-hidden="true" />
                    </Button>
                </div>
            ))}
        </div>
    );
}
