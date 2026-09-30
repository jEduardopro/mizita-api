import { cn } from 'cn';
import { Share } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useShareLink } from '@/hooks/use-share-link';

type Props = {
    url: string;
    title: string;
    label: string;
    copied: string;
    copyFailed: string;
    className?: string;
};

export function BookingShareButton({ url, title, label, copied, copyFailed, className }: Props) {
    const share = useShareLink({ title, copied, failed: copyFailed });

    return (
        <Button
            type="button"
            variant="ghost"
            onClick={() => share(url)}
            className={cn('size-11 rounded-full p-0', className)}
        >
            <Share aria-hidden="true" className="size-5" />
            <span className="sr-only">{label}</span>
        </Button>
    );
}
