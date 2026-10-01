import { enUS } from 'date-fns/locale/en-US';
import { es } from 'date-fns/locale/es';
import type { Locale } from 'react-day-picker';

const CALENDAR_LOCALES: Record<string, Locale> = {
    en: enUS,
    es,
};

export function calendarLocale(language: string): Locale {
    return CALENDAR_LOCALES[language.split('-')[0]] ?? enUS;
}
