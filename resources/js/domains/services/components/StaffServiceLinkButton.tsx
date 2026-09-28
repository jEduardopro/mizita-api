import { cn } from 'cn';
import { Link2 } from 'lucide-react';
import { useState, type MouseEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useCopyToClipboard } from '@/hooks/use-copy-to-clipboard';

const ICON_BUTTON = 'size-11 shrink-0 p-0 text-muted-foreground md:size-9';

type UnavailableProps = {
    label: string;
    hint: string;
};

function UnavailableLinkButton({ label, hint }: UnavailableProps) {
    const [isHintOpen, setIsHintOpen] = useState(false);

    function revealHint(event: MouseEvent<HTMLButtonElement>) {
        event.preventDefault();
        setIsHintOpen(true);
    }

    return (
        <Tooltip open={isHintOpen} onOpenChange={setIsHintOpen}>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    aria-disabled="true"
                    aria-label={label}
                    onClick={revealHint}
                    className={cn(ICON_BUTTON, 'cursor-not-allowed opacity-50')}
                >
                    <Link2 aria-hidden="true" />
                </Button>
            </TooltipTrigger>

            <TooltipContent>{hint}</TooltipContent>
        </Tooltip>
    );
}

type Props = {
    serviceName: string;
    url: string | null;
    staffName: string;
};

export function StaffServiceLinkButton({ serviceName, url, staffName }: Props) {
    const { t } = useTranslation('admin');
    const copy = useCopyToClipboard({
        copied: t('services.toasts.linkCopied'),
        failed: t('staffServices.copyFailed'),
    });

    const label = t('staffServices.copyLink', { service: serviceName });

    if (url === null) {
        return <UnavailableLinkButton label={label} hint={t('staffServices.linkUnavailable', { name: staffName })} />;
    }

    return (
        <Button
            type="button"
            variant="ghost"
            onClick={() => copy(url)}
            aria-label={label}
            className={cn(ICON_BUTTON, 'hover:text-foreground')}
        >
            <Link2 aria-hidden="true" />
        </Button>
    );
}
