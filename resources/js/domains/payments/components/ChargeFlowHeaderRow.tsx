import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    backLabel: string;
    onBack: () => void;
    children: ReactNode;
};

export function ChargeFlowHeaderRow({ backLabel, onBack, children }: Props) {
    return (
        <div className="flex items-center gap-1 pr-9">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onBack}
                aria-label={backLabel}
                className="-ml-2 size-11 shrink-0 rounded-full md:size-9"
            >
                <ChevronLeft />
            </Button>

            {children}
        </div>
    );
}
