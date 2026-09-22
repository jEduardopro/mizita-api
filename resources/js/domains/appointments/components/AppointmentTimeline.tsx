import { Fragment } from 'react';
import { AppointmentTimelineDay } from './AppointmentTimelineDay';
import { AppointmentTimelineYear } from './AppointmentTimelineYear';
import type { AppointmentDayGroup } from './appointment-timeline-groups';
import type { Appointment } from '../types';

const FIRST_GROUP_INDEX = 0;

type Props = {
    groups: AppointmentDayGroup[];
    timezone: string;
    onSelect: (appointment: Appointment) => void;
};

export function AppointmentTimeline({ groups, timezone, onSelect }: Props) {
    return (
        <ol className="grid grid-cols-[3.25rem_1fr] gap-x-3 gap-y-5 sm:grid-cols-[3.75rem_1fr] sm:gap-x-4">
            {groups.map((group, index) => {
                const previousGroup = groups.at(index - 1);
                const opensNewYear =
                    index > FIRST_GROUP_INDEX &&
                    previousGroup !== undefined &&
                    previousGroup.year !== group.year;

                return (
                    <Fragment key={group.date}>
                        {opensNewYear ? <AppointmentTimelineYear year={group.year} /> : null}

                        <AppointmentTimelineDay
                            group={group}
                            timezone={timezone}
                            onSelect={onSelect}
                        />
                    </Fragment>
                );
            })}
        </ol>
    );
}
