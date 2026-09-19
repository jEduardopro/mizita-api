import { cn } from 'cn';
import { CalendarPlus, Download, ExternalLink } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    BUTTON_SHAPE_CLASSES,
    type BrandColorClasses,
    type ButtonShape,
} from '@/lib/booking-brand';
import {
    googleCalendarUrl,
    icsCalendarFor,
    icsFileNameFor,
    type CalendarEvent,
} from './calendar-links';

type Props = {
    event: CalendarEvent;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
};

const ICS_MEDIA_TYPE = 'text/calendar;charset=utf-8';

function downloadIcsFile(event: CalendarEvent): void {
    const blob = new Blob([icsCalendarFor(event, new Date().toISOString())], {
        type: ICS_MEDIA_TYPE,
    });
    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = objectUrl;
    anchor.download = icsFileNameFor(event.uid);
    anchor.rel = 'noreferrer';

    document.body.append(anchor);
    anchor.click();
    anchor.remove();

    URL.revokeObjectURL(objectUrl);
}

export function AddToCalendarMenu({ event, accent, buttonShape }: Props) {
    const { t } = useTranslation('public');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    'flex h-12 w-full items-center justify-center gap-2 px-6 text-base font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring/50 motion-safe:transition-opacity hover:opacity-90 sm:w-auto',
                    accent.accent,
                    accent.accentForeground,
                    BUTTON_SHAPE_CLASSES[buttonShape],
                )}
            >
                <CalendarPlus aria-hidden="true" className="size-4" />

                {t('booking.flow.confirmed.calendar', { defaultValue: 'Add to calendar' })}
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" className="w-64">
                <DropdownMenuItem asChild className="h-11 px-3">
                    <a
                        href={googleCalendarUrl(event)}
                        target="_blank"
                        rel="noreferrer"
                        className="flex items-center gap-2"
                    >
                        <ExternalLink aria-hidden="true" />

                        {t('booking.flow.confirmed.calendarGoogle', {
                            defaultValue: 'Google Calendar',
                        })}
                    </a>
                </DropdownMenuItem>

                <DropdownMenuItem
                    className="h-11 gap-2 px-3"
                    onSelect={() => downloadIcsFile(event)}
                >
                    <Download aria-hidden="true" />

                    {t('booking.flow.confirmed.calendarDownload', {
                        defaultValue: 'Apple, Outlook or other (.ics)',
                    })}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
