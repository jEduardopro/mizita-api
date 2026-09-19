import { enUS } from 'date-fns/locale/en-US';
import { es } from 'date-fns/locale/es';
import { Check, ChevronLeft, ChevronRight, Columns3, Grid3x3, PanelTop } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { Locale } from 'react-day-picker';
import { useTranslation } from 'react-i18next';
import { cn } from 'cn';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { CalendarViewName } from './AppointmentCalendar';

const CALENDAR_LOCALES: Record<string, Locale> = {
    en: enUS,
    es,
};

const TODAY_DATE_FORMAT: Intl.DateTimeFormatOptions = {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
};

function calendarLocale(language: string): Locale {
    return CALENDAR_LOCALES[language.split('-')[0]] ?? enUS;
}

function capitalizeFirstLetter(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatTodayInTimezone(language: string, timezone: string): string {
    const formatter = new Intl.DateTimeFormat(language, { ...TODAY_DATE_FORMAT, timeZone: timezone });

    return capitalizeFirstLetter(formatter.format(new Date()));
}

type ViewOption = {
    value: CalendarViewName;
    icon: LucideIcon;
    labelKey: 'calendar.views.day' | 'calendar.views.week' | 'calendar.views.month';
    shortcut: string;
};

const VIEW_OPTIONS: ViewOption[] = [
    { value: 'day', icon: PanelTop, labelKey: 'calendar.views.day', shortcut: 'D' },
    { value: 'week', icon: Columns3, labelKey: 'calendar.views.week', shortcut: 'W' },
    { value: 'month-grid', icon: Grid3x3, labelKey: 'calendar.views.month', shortcut: 'M' },
];

const FALLBACK_VIEW_ICON: LucideIcon = Columns3;

function viewIcon(view: CalendarViewName): LucideIcon {
    const option = VIEW_OPTIONS.find((candidate) => candidate.value === view);

    if (option === undefined) {
        return FALLBACK_VIEW_ICON;
    }

    return option.icon;
}

type Props = {
    label: string;
    view: CalendarViewName;
    onViewChange: (view: CalendarViewName) => void;
    onToday: () => void;
    onPrevious: () => void;
    onNext: () => void;
    selectedDate: Date;
    onSelectDate: (date: Date) => void;
    timezone: string;
};

export function CalendarToolbar({
    label,
    view,
    onViewChange,
    onToday,
    onPrevious,
    onNext,
    selectedDate,
    onSelectDate,
    timezone,
}: Props) {
    const { t, i18n } = useTranslation('admin');
    const [pickerOpen, setPickerOpen] = useState(false);
    const [shownMonth, setShownMonth] = useState(selectedDate);
    const [viewMenuOpen, setViewMenuOpen] = useState(false);
    const locale = useMemo(() => calendarLocale(i18n.language), [i18n.language]);
    const ActiveViewIcon = viewIcon(view);

    function onPickerOpenChange(next: boolean) {
        if (next) {
            setShownMonth(selectedDate);
        }

        setPickerOpen(next);
    }

    return (
        <div className="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-2 sm:grid sm:grid-cols-[1fr_auto_1fr] sm:flex-nowrap sm:gap-3 sm:px-4">
            <div className="hidden sm:block" aria-hidden="true" />

            <div className="flex items-center gap-1">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onToday}
                            className="h-11 px-3 md:h-9"
                        >
                            {t('calendar.actions.today')}
                        </Button>
                    </TooltipTrigger>

                    <TooltipContent>{formatTodayInTimezone(i18n.language, timezone)}</TooltipContent>
                </Tooltip>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('calendar.actions.previous')}
                    onClick={onPrevious}
                    className="size-11 md:size-9"
                >
                    <ChevronLeft aria-hidden="true" />
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('calendar.actions.next')}
                    onClick={onNext}
                    className="size-11 md:size-9"
                >
                    <ChevronRight aria-hidden="true" />
                </Button>

                <Popover open={pickerOpen} onOpenChange={onPickerOpenChange}>
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            aria-label={t('calendar.actions.pickDate')}
                            className="h-11 px-3 text-sm font-medium md:h-9"
                        >
                            {label}
                        </Button>
                    </PopoverTrigger>

                    <PopoverContent align="start" className="w-auto gap-0 p-0 shadow-lg">
                        <Calendar
                            mode="single"
                            selected={selectedDate}
                            onSelect={(date) => {
                                if (date === undefined) {
                                    return;
                                }

                                onSelectDate(date);
                                setPickerOpen(false);
                            }}
                            month={shownMonth}
                            onMonthChange={setShownMonth}
                            captionLayout="dropdown"
                            locale={locale}
                            className="p-3 [--cell-size:min(--spacing(11),calc((100vw-3rem)/7))]"
                        />
                    </PopoverContent>
                </Popover>
            </div>

            <div className="flex items-center justify-end">
                <Popover open={viewMenuOpen} onOpenChange={setViewMenuOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={t('calendar.actions.changeView')}
                            className="size-11 md:size-9"
                        >
                            <ActiveViewIcon aria-hidden="true" />
                        </Button>
                    </PopoverTrigger>

                    <PopoverContent align="end" className="w-48 gap-0 p-1">
                        <div role="menu" aria-label={t('calendar.actions.changeView')} className="grid gap-0.5">
                            {VIEW_OPTIONS.map(({ value, icon: OptionIcon, labelKey, shortcut }) => (
                                <button
                                    key={value}
                                    type="button"
                                    role="menuitemradio"
                                    aria-checked={view === value}
                                    onClick={() => {
                                        onViewChange(value);
                                        setViewMenuOpen(false);
                                    }}
                                    className="flex min-h-11 w-full items-center gap-2 rounded-md px-2 text-sm outline-none hover:bg-muted focus-visible:bg-muted"
                                >
                                    <Check
                                        className={cn('size-4 shrink-0', view === value ? 'opacity-100' : 'opacity-0')}
                                        aria-hidden="true"
                                    />

                                    <OptionIcon className="size-4 shrink-0" aria-hidden="true" />

                                    <span className="flex-1 text-left">{t(labelKey)}</span>

                                    <span className="text-xs text-muted-foreground">{shortcut}</span>
                                </button>
                            ))}
                        </div>
                    </PopoverContent>
                </Popover>
            </div>
        </div>
    );
}
