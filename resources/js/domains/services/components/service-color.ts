import type { ServiceColor } from '../types';

type ServiceColorClasses = {
    bar: string;
    tile: string;
    icon: string;
};

export const serviceColorClasses: Record<ServiceColor, ServiceColorClasses> = {
    red: {
        bar: 'bg-service-red',
        tile: 'bg-service-red-surface',
        icon: 'text-service-red',
    },
    orange: {
        bar: 'bg-service-orange',
        tile: 'bg-service-orange-surface',
        icon: 'text-service-orange',
    },
    amber: {
        bar: 'bg-service-amber',
        tile: 'bg-service-amber-surface',
        icon: 'text-service-amber',
    },
    purple: {
        bar: 'bg-service-purple',
        tile: 'bg-service-purple-surface',
        icon: 'text-service-purple',
    },
    blue: {
        bar: 'bg-service-blue',
        tile: 'bg-service-blue-surface',
        icon: 'text-service-blue',
    },
    sand: {
        bar: 'bg-service-sand',
        tile: 'bg-service-sand-surface',
        icon: 'text-service-sand',
    },
    slate: {
        bar: 'bg-service-slate',
        tile: 'bg-service-slate-surface',
        icon: 'text-service-slate',
    },
    teal: {
        bar: 'bg-service-teal',
        tile: 'bg-service-teal-surface',
        icon: 'text-service-teal',
    },
    green: {
        bar: 'bg-service-green',
        tile: 'bg-service-green-surface',
        icon: 'text-service-green',
    },
};
