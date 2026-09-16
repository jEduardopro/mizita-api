import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { GalleryImage } from '@/domains/businesses/types';

type Props = {
    images: GalleryImage[];
    onMoveUp: (id: string) => void;
    onMoveDown: (id: string) => void;
    onRemove: (id: string) => void;
};

export function GallerySheet({ images, onMoveUp, onMoveDown, onRemove }: Props) {
    const { t } = useTranslation('admin');

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button type="button" variant="outline" className="h-11 px-4 md:h-10">
                    {t('businessSettings.gallery.showAll')}
                </Button>
            </SheetTrigger>

            <SheetContent
                side="bottom"
                className="max-h-[85svh] pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>{t('businessSettings.gallery.sheetTitle')}</SheetTitle>
                    <SheetDescription>
                        {t('businessSettings.gallery.sheetDescription')}
                    </SheetDescription>
                </SheetHeader>

                <ul className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-4">
                    {images.map((image, index) => {
                        const position = index + 1;

                        return (
                            <li
                                key={image.id}
                                className="flex items-center gap-3 border-b border-border py-2 last:border-b-0"
                            >
                                <img
                                    src={image.url}
                                    alt=""
                                    className="size-14 shrink-0 rounded-lg object-cover"
                                />

                                <span className="w-5 shrink-0 text-sm tabular-nums text-muted-foreground">
                                    {position}
                                </span>

                                <span className="flex flex-1 items-center justify-end gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => onMoveUp(image.id)}
                                        disabled={index === 0}
                                        aria-label={t('businessSettings.gallery.moveUp', { position })}
                                        className="size-11 shrink-0 p-0"
                                    >
                                        <ArrowUp aria-hidden="true" />
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => onMoveDown(image.id)}
                                        disabled={index === images.length - 1}
                                        aria-label={t('businessSettings.gallery.moveDown', { position })}
                                        className="size-11 shrink-0 p-0"
                                    >
                                        <ArrowDown aria-hidden="true" />
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => onRemove(image.id)}
                                        aria-label={t('businessSettings.gallery.remove', { position })}
                                        className="size-11 shrink-0 p-0 text-muted-foreground hover:text-destructive"
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                </span>
                            </li>
                        );
                    })}
                </ul>
            </SheetContent>
        </Sheet>
    );
}
