import { Dialog, DialogContent } from '@/components/ui/dialog';
import { EditBookingSlugForm } from './EditBookingSlugForm';

const CONTENT_CLASSES =
    'top-auto bottom-0 flex max-h-[min(92svh,32rem)] max-w-none translate-y-0 flex-col gap-0 overflow-hidden rounded-b-none p-0 sm:top-1/2 sm:bottom-auto sm:max-w-md sm:-translate-y-1/2 sm:rounded-b-xl';

type Props = {
    staffMemberId: string;
    slug: string;
    url: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function EditBookingSlugDialog({ staffMemberId, slug, url, open, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent showCloseButton={false} className={CONTENT_CLASSES}>
                <EditBookingSlugForm
                    staffMemberId={staffMemberId}
                    slug={slug}
                    url={url}
                    onSaved={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
