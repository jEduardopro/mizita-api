import { useId } from 'react';
import { useTranslation } from 'react-i18next';

const STEPS = ['connect', 'approve', 'find', 'block', 'disconnect'] as const;

type Step = (typeof STEPS)[number];

const STEP_KEYS = {
    connect: 'integrations.googleCalendar.instructions.connect',
    approve: 'integrations.googleCalendar.instructions.approve',
    find: 'integrations.googleCalendar.instructions.find',
    block: 'integrations.googleCalendar.instructions.block',
    disconnect: 'integrations.googleCalendar.instructions.disconnect',
} as const satisfies Record<Step, string>;

type Props = {
    calendarName: string;
};

export function GoogleCalendarInstructions({ calendarName }: Props) {
    const { t } = useTranslation('admin');
    const headingId = useId();

    return (
        <section aria-labelledby={headingId} className="grid gap-4">
            <h3 id={headingId} className="text-sm font-semibold">
                {t('integrations.googleCalendar.instructions.title')}
            </h3>

            <ol className="grid gap-4">
                {STEPS.map((step, index) => (
                    <li key={step} className="grid grid-cols-[auto_minmax(0,1fr)] gap-3">
                        <span
                            aria-hidden="true"
                            className="grid size-7 place-items-center rounded-full bg-muted text-xs font-semibold tabular-nums"
                        >
                            {index + 1}
                        </span>

                        <p className="pt-1 text-sm text-pretty break-words text-foreground/80">
                            {t(STEP_KEYS[step], { calendar: calendarName })}
                        </p>
                    </li>
                ))}
            </ol>
        </section>
    );
}
