import { useTranslation } from 'react-i18next';
import { GoogleCalendarAbout } from './GoogleCalendarAbout';
import { GoogleCalendarConnectionPanel } from './GoogleCalendarConnectionPanel';
import { GoogleCalendarIcon } from './GoogleCalendarIcon';
import { GoogleCalendarInstructions } from './GoogleCalendarInstructions';
import type { IntegrationDetailProps } from './integration-catalog';
import { IntegrationDetailSurface } from './IntegrationDetailSurface';
import { IntegrationDetailTabs } from './IntegrationDetailTabs';

export function GoogleCalendarDetail({ connection, businessName, open, onOpenChange }: IntegrationDetailProps) {
    const { t } = useTranslation('admin');

    const calendarName =
        businessName === null
            ? t('integrations.googleCalendar.calendarNameFallback')
            : t('integrations.googleCalendar.calendarName', { business: businessName });

    return (
        <IntegrationDetailSurface
            open={open}
            onOpenChange={onOpenChange}
            logo={<GoogleCalendarIcon />}
            name={t('integrations.googleCalendar.name')}
            tagline={t('integrations.googleCalendar.tagline')}
            main={
                <IntegrationDetailTabs
                    about={<GoogleCalendarAbout calendarName={calendarName} />}
                    instructions={<GoogleCalendarInstructions calendarName={calendarName} />}
                />
            }
            aside={<GoogleCalendarConnectionPanel connection={connection} calendarName={calendarName} />}
        />
    );
}
