import { createCalendarControlsPlugin } from '@schedule-x/calendar-controls';
import { viewDay, viewMonthGrid, viewWeek, type CalendarConfig } from '@schedule-x/calendar';
import { createCurrentTimePlugin } from '@schedule-x/current-time';
import { ScheduleXCalendar, useCalendarApp } from '@schedule-x/react';
import '@schedule-x/theme-shadcn/dist/index.css';
import 'temporal-polyfill/global';
import { cn } from 'cn';
import { forwardRef, useEffect, useImperativeHandle, useMemo, useRef } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import {
    APPOINTMENT_CALENDARS,
    appointmentsToEvents,
    isAppointmentCalendarEvent,
} from './appointment-events';
import { SLOT_MINUTES } from './appointment-slots';
import { businessHoursBackgroundEvents, type BusinessScheduleRule } from './business-hours';
import { CalendarHourLabel, type CalendarGridStep } from './CalendarHourLabel';
import { MonthGridEventContent } from './MonthGridEventContent';
import { SlotHoverPreview } from './SlotHoverPreview';
import { TimeGridEventContent } from './TimeGridEventContent';
import { useCalendarInitialScroll } from './use-calendar-initial-scroll';
import type { Appointment, AppointmentRange } from '../types';

export type CalendarViewName = 'day' | 'week' | 'month-grid';

const SCHEDULE_X_LOCALES: Record<string, string> = {
    en: 'en-US',
    es: 'es-ES',
};

const DEFAULT_SCHEDULE_X_LOCALE = 'en-US';

const MINUTES_PER_DAY = 24 * 60;

const SLOT_PIXEL_HEIGHT = 30;

const GRID_PIXEL_HEIGHT = (MINUTES_PER_DAY / SLOT_MINUTES) * SLOT_PIXEL_HEIGHT;

function scheduleXLocale(locale: string): string {
    return SCHEDULE_X_LOCALES[locale] ?? DEFAULT_SCHEDULE_X_LOCALE;
}

export type AppointmentCalendarHandle = {
    goToToday: () => void;
    goToDate: (date: string) => void;
    shift: (direction: 1 | -1) => void;
    setView: (view: CalendarViewName) => void;
};

type Props = {
    appointments: Appointment[];
    timezone: string;
    locale: string;
    schedule: BusinessScheduleRule[];
    initialView: CalendarViewName;
    className?: string;
    onRangeChange: (range: AppointmentRange) => void;
    onSelectedDateChange: (date: string, view: CalendarViewName) => void;
    onClickSlot?: (startsAt: string) => void;
    onClickAppointment: (appointment: Appointment) => void;
};

function EmptyHeader() {
    return null;
}

export const AppointmentCalendar = forwardRef<AppointmentCalendarHandle, Props>(
    function AppointmentCalendar(
        {
            appointments,
            timezone,
            locale,
            schedule,
            initialView,
            className,
            onRangeChange,
            onSelectedDateChange,
            onClickSlot,
            onClickAppointment,
        },
        ref,
    ) {
        const { resolvedAppearance } = useAppearance();
        const controls = useMemo(() => createCalendarControlsPlugin(), []);
        const currentTime = useMemo(() => createCurrentTimePlugin({ fullWeekWidth: true }), []);
        const plugins = useMemo(() => [controls, currentTime], [controls, currentTime]);

        const weekGridHour = useMemo(
            () =>
                function WeekGridHour({ gridStep }: { gridStep: CalendarGridStep }) {
                    return <CalendarHourLabel gridStep={gridStep} locale={locale} />;
                },
            [locale],
        );

        const customComponents = useMemo(
            () => ({
                headerContent: EmptyHeader,
                monthGridEvent: MonthGridEventContent,
                timeGridEvent: TimeGridEventContent,
                weekGridHour,
            }),
            [weekGridHour],
        );

        const wrapperRef = useRef<HTMLDivElement>(null);

        useCalendarInitialScroll(wrapperRef, timezone);

        const callbacksRef = useRef({ onRangeChange, onSelectedDateChange, onClickSlot, onClickAppointment });
        callbacksRef.current = { onRangeChange, onSelectedDateChange, onClickSlot, onClickAppointment };

        const backgroundEvents = useMemo(
            () => businessHoursBackgroundEvents(schedule, timezone),
            [schedule, timezone],
        );

        const config = useMemo<CalendarConfig>(
            () => ({
                views: [viewDay, viewWeek, viewMonthGrid],
                defaultView: initialView,
                timezone,
                locale: scheduleXLocale(locale),
                theme: 'shadcn',
                isDark: resolvedAppearance === 'dark',
                weekOptions: { gridStep: SLOT_MINUTES, gridHeight: GRID_PIXEL_HEIGHT },
                calendars: APPOINTMENT_CALENDARS,
                backgroundEvents,
                callbacks: {
                    onRangeUpdate: (range) => {
                        callbacksRef.current.onRangeChange({
                            from: range.start.toInstant().toString(),
                            to: range.end.toInstant().toString(),
                        });
                    },
                    onSelectedDateUpdate: (date) => {
                        callbacksRef.current.onSelectedDateChange(date.toString(), controls.getView() as CalendarViewName);
                    },
                    onClickDateTime: (dateTime) => {
                        const snapped = dateTime.round({
                            smallestUnit: 'minute',
                            roundingIncrement: SLOT_MINUTES,
                            roundingMode: 'floor',
                        });

                        callbacksRef.current.onClickSlot?.(snapped.toInstant().toString());
                    },
                    onEventClick: (event) => {
                        if (isAppointmentCalendarEvent(event)) {
                            callbacksRef.current.onClickAppointment(event.appointment);
                        }
                    },
                },
            }),
            [timezone, locale, initialView, backgroundEvents, controls, resolvedAppearance],
        );

        const calendarApp = useCalendarApp(config, plugins);

        const skipTimezoneSync = useRef(true);
        const skipLocaleSync = useRef(true);

        useEffect(() => {
            calendarApp?.events.set(appointmentsToEvents(appointments, timezone));
        }, [calendarApp, appointments, timezone]);

        useEffect(() => {
            if (! calendarApp) {
                return;
            }

            if (skipTimezoneSync.current) {
                skipTimezoneSync.current = false;

                return;
            }

            controls.setTimezone(timezone);
        }, [calendarApp, controls, timezone]);

        useEffect(() => {
            if (! calendarApp) {
                return;
            }

            if (skipLocaleSync.current) {
                skipLocaleSync.current = false;

                return;
            }

            controls.setLocale(scheduleXLocale(locale));
        }, [calendarApp, controls, locale]);

        useEffect(() => {
            calendarApp?.setTheme(resolvedAppearance);
        }, [calendarApp, resolvedAppearance]);

        useImperativeHandle(
            ref,
            () => ({
                goToToday: () => controls.setDate(Temporal.Now.plainDateISO(timezone)),
                goToDate: (date) => controls.setDate(Temporal.PlainDate.from(date)),
                shift: (direction) => {
                    const view = controls.getView();
                    const current = controls.getDate();

                    if (view === 'day') {
                        controls.setDate(current.add({ days: direction }));

                        return;
                    }

                    if (view === 'week') {
                        controls.setDate(current.add({ days: 7 * direction }));

                        return;
                    }

                    controls.setDate(current.add({ months: direction }));
                },
                setView: (view) => controls.setView(view),
            }),
            [controls, timezone],
        );

        return (
            <div ref={wrapperRef} className={cn('h-full', className)}>
                <ScheduleXCalendar calendarApp={calendarApp} customComponents={customComponents} />

                {onClickSlot === undefined ? null : (
                    <SlotHoverPreview containerRef={wrapperRef} locale={locale} />
                )}
            </div>
        );
    },
);
