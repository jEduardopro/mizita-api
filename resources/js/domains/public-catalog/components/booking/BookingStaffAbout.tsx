import { cn } from 'cn';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { useExpandableText } from './use-expandable-text';

type Props = {
    text: string;
};

export function BookingStaffAbout({ text }: Props) {
    const { t } = useTranslation('public');
    const textId = useId();
    const { ref, isExpanded, canExpand, toggle } = useExpandableText();

    return (
        <div className="grid justify-items-start">
            <p
                id={textId}
                ref={ref}
                className={cn(
                    'text-sm leading-relaxed whitespace-pre-line text-pretty break-words text-foreground/80',
                    ! isExpanded && 'line-clamp-4',
                )}
            >
                {text}
            </p>

            {canExpand ? (
                <button
                    type="button"
                    aria-expanded={isExpanded}
                    aria-controls={textId}
                    onClick={toggle}
                    className="-ml-1.5 inline-flex h-11 items-center rounded-md px-1.5 text-sm font-medium underline underline-offset-4 outline-none hover:no-underline focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                    {isExpanded ? t('booking.flow.pinned.readLess') : t('booking.flow.pinned.readMore')}
                </button>
            ) : null}
        </div>
    );
}
