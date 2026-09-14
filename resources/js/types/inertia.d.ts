import '@inertiajs/core';

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            name: string;
            status: string | null;
            locale: string;
            supportedLocales: string[];
            flash: { error: string | null };
        };
    }
}
