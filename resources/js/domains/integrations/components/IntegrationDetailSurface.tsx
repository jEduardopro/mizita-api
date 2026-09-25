import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import { IntegrationLogoTile } from './IntegrationLogoTile';

const TITLE_CLASSES = 'text-lg leading-tight font-semibold tracking-tight';

const DESCRIPTION_CLASSES = 'text-sm text-pretty text-muted-foreground';

type FrameProps = {
    logo: ReactNode;
    title: ReactNode;
    description: ReactNode;
    main: ReactNode;
    aside: ReactNode;
};

function IntegrationDetailFrame({ logo, title, description, main, aside }: FrameProps) {
    const { t } = useTranslation('admin');

    return (
        <>
            <header className="flex shrink-0 items-start gap-3 border-b border-border px-5 py-4 md:gap-4 md:px-6 md:py-5">
                <IntegrationLogoTile>{logo}</IntegrationLogoTile>

                <div className="grid min-w-0 flex-1 gap-1 self-center">
                    {title}
                    {description}
                </div>

                <DialogClose asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        aria-label={t('integrations.detail.close')}
                        className="-mt-1 -mr-3 size-11 shrink-0 p-0"
                    >
                        <X aria-hidden="true" className="size-5" />
                    </Button>
                </DialogClose>
            </header>

            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain md:grid md:grid-cols-[minmax(0,1fr)_18rem] md:overflow-hidden">
                <div className="px-5 py-5 md:overflow-y-auto md:px-6">{main}</div>

                <aside className="border-t border-border bg-muted/40 px-5 py-6 md:overflow-y-auto md:border-t-0 md:border-l md:py-5">
                    {aside}
                </aside>
            </div>
        </>
    );
}

type Props = Omit<FrameProps, 'title' | 'description'> & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    name: string;
    tagline: string;
};

export function IntegrationDetailSurface({ open, onOpenChange, name, tagline, ...frame }: Props) {
    const isDesktop = useIsDesktop();

    if (isDesktop) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent
                    showCloseButton={false}
                    onOpenAutoFocus={(event) => event.preventDefault()}
                    className="flex h-[min(42rem,calc(100svh-4rem))] flex-col gap-0 overflow-hidden p-0 sm:max-w-[min(56rem,calc(100%-2rem))]"
                >
                    <IntegrationDetailFrame
                        {...frame}
                        title={<DialogTitle className={TITLE_CLASSES}>{name}</DialogTitle>}
                        description={
                            <DialogDescription className={DESCRIPTION_CLASSES}>{tagline}</DialogDescription>
                        }
                    />
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                showCloseButton={false}
                onOpenAutoFocus={(event) => event.preventDefault()}
                className="gap-0 overflow-hidden rounded-t-2xl pb-[env(safe-area-inset-bottom)] data-[side=bottom]:h-[calc(100svh-1.5rem)]"
            >
                <IntegrationDetailFrame
                    {...frame}
                    title={<SheetTitle className={TITLE_CLASSES}>{name}</SheetTitle>}
                    description={<SheetDescription className={DESCRIPTION_CLASSES}>{tagline}</SheetDescription>}
                />
            </SheetContent>
        </Sheet>
    );
}
