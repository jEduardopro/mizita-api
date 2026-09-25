import { CalendarX2, LockKeyhole, Send, UserRound, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CalendarSyncDiagram } from './CalendarSyncDiagram';

const FACTS = ['oneWay', 'blocks', 'privacy', 'otherCalendars'] as const;

type Fact = (typeof FACTS)[number];

const FACT_ICONS = {
    oneWay: Send,
    blocks: CalendarX2,
    privacy: UserRound,
    otherCalendars: LockKeyhole,
} as const satisfies Record<Fact, LucideIcon>;

const FACT_TITLE_KEYS = {
    oneWay: 'integrations.googleCalendar.about.oneWay.title',
    blocks: 'integrations.googleCalendar.about.blocks.title',
    privacy: 'integrations.googleCalendar.about.privacy.title',
    otherCalendars: 'integrations.googleCalendar.about.otherCalendars.title',
} as const satisfies Record<Fact, string>;

const FACT_BODY_KEYS = {
    oneWay: 'integrations.googleCalendar.about.oneWay.body',
    blocks: 'integrations.googleCalendar.about.blocks.body',
    privacy: 'integrations.googleCalendar.about.privacy.body',
    otherCalendars: 'integrations.googleCalendar.about.otherCalendars.body',
} as const satisfies Record<Fact, string>;

type Props = {
    calendarName: string;
};

export function GoogleCalendarAbout({ calendarName }: Props) {
    const { t } = useTranslation('admin');

    return (
        <div className="grid gap-6">
            <p className="text-sm text-pretty text-foreground/80">
                {t('integrations.googleCalendar.about.intro', { calendar: calendarName })}
            </p>

            <CalendarSyncDiagram />

            <ul className="grid gap-5 sm:grid-cols-2 sm:gap-x-6">
                {FACTS.map((fact) => {
                    const Icon = FACT_ICONS[fact];

                    return (
                        <li key={fact} className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
                            <span className="row-span-2 grid size-8 place-items-center rounded-lg bg-muted">
                                <Icon aria-hidden="true" className="size-4 text-foreground/80" />
                            </span>

                            <p className="text-sm font-medium">{t(FACT_TITLE_KEYS[fact])}</p>

                            <p className="text-sm text-pretty text-muted-foreground">{t(FACT_BODY_KEYS[fact])}</p>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
