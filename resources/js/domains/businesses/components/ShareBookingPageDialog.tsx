import { LinkIcon, MailIcon, XIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    FacebookIcon,
    MessengerIcon,
    WhatsappIcon,
    type SocialIconProps,
} from '@/components/shared/SocialIcons';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useCopyToClipboard } from '@/hooks/use-copy-to-clipboard';
import { useIsMobile } from '@/hooks/use-mobile';
import { bookingPageHost, bookingPageUrl } from './settings/booking-page-url';
import {
    emailShareHref,
    facebookShareHref,
    messengerShareHref,
    whatsappShareHref,
} from './share-booking-page-links';

const CONTENT_CLASSES =
    'top-auto bottom-0 left-0 max-h-[90svh] max-w-none translate-x-0 translate-y-0 gap-5 overflow-y-auto overscroll-contain rounded-b-none p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:max-w-md sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-b-xl sm:p-6';

const PILL_CLASSES = 'h-11 w-full gap-2 rounded-full px-4';

type ShareTarget = {
    id: string;
    label: string;
    href: string;
    icon: (props: SocialIconProps) => ReactNode;
};

type ShareLinkPillProps = Omit<ShareTarget, 'id'>;

function ShareLinkPill({ label, href, icon: Icon }: ShareLinkPillProps) {
    return (
        <Button asChild variant="outline" className={PILL_CLASSES}>
            <a href={href} target="_blank" rel="noopener noreferrer">
                <Icon aria-hidden="true" />
                {label}
            </a>
        </Button>
    );
}

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    name: string;
    slug: string;
    children: ReactNode;
};

export function ShareBookingPageDialog({ open, onOpenChange, name, slug, children }: Props) {
    const { t } = useTranslation('admin');
    const isMobile = useIsMobile();

    const copy = useCopyToClipboard({
        copied: t('shell.shareBookingPage.copied'),
        failed: t('shell.shareBookingPage.copyFailed'),
    });

    const url = bookingPageUrl(slug);

    const messengerTarget: ShareTarget = {
        id: 'messenger',
        label: t('shell.shareBookingPage.messenger'),
        href: messengerShareHref(url),
        icon: MessengerIcon,
    };

    const shareTargets: ShareTarget[] = [
        {
            id: 'email',
            label: t('shell.shareBookingPage.email'),
            href: emailShareHref(
                t('shell.shareBookingPage.emailSubject', { name }),
                t('shell.shareBookingPage.emailBody', { name, url }),
            ),
            icon: MailIcon,
        },
        {
            id: 'facebook',
            label: t('shell.shareBookingPage.facebook'),
            href: facebookShareHref(url),
            icon: FacebookIcon,
        },
        ...(isMobile ? [messengerTarget] : []),
        {
            id: 'whatsapp',
            label: t('shell.shareBookingPage.whatsapp'),
            href: whatsappShareHref(t('shell.shareBookingPage.message', { name, url })),
            icon: WhatsappIcon,
        },
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent showCloseButton={false} className={CONTENT_CLASSES}>
                <DialogHeader className="gap-4 pr-10">
                    <DialogTitle className="text-lg leading-snug text-balance">
                        {t('shell.shareBookingPage.title')}
                    </DialogTitle>

                    <DialogDescription className="grid gap-1 text-base">
                        <span className="font-medium text-foreground">{name}</span>
                        <span className="break-all">
                            {bookingPageHost()}/
                            <span className="font-medium text-primary">{slug}</span>
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2 sm:grid-cols-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => copy(url)}
                        className={PILL_CLASSES}
                    >
                        <LinkIcon aria-hidden="true" />
                        {t('shell.shareBookingPage.copy')}
                    </Button>

                    {shareTargets.map(({ id, ...target }) => (
                        <ShareLinkPill key={id} {...target} />
                    ))}
                </div>

                <DialogClose asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        aria-label={t('shell.shareBookingPage.close')}
                        className="absolute top-2 right-2 size-11"
                    >
                        <XIcon aria-hidden="true" className="size-5" />
                    </Button>
                </DialogClose>
            </DialogContent>
        </Dialog>
    );
}
