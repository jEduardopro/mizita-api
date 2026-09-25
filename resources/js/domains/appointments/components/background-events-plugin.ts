import type { BackgroundEvent, PluginBase } from '@schedule-x/calendar';

const BACKGROUND_EVENTS_PLUGIN_NAME = 'backgroundEvents';

type BackgroundEventsHost = {
    calendarEvents: {
        backgroundEvents: { value: BackgroundEvent[] };
    };
};

export type BackgroundEventsPlugin = PluginBase<typeof BACKGROUND_EVENTS_PLUGIN_NAME> & {
    set: (events: BackgroundEvent[]) => void;
};

export function createBackgroundEventsPlugin(): BackgroundEventsPlugin {
    let host: BackgroundEventsHost | null = null;
    let pendingEvents: BackgroundEvent[] | null = null;

    return {
        name: BACKGROUND_EVENTS_PLUGIN_NAME,
        beforeRender($app: BackgroundEventsHost) {
            host = $app;

            if (pendingEvents === null) {
                return;
            }

            host.calendarEvents.backgroundEvents.value = pendingEvents;
            pendingEvents = null;
        },
        set(events) {
            if (host === null) {
                pendingEvents = events;

                return;
            }

            host.calendarEvents.backgroundEvents.value = events;
        },
    };
}
