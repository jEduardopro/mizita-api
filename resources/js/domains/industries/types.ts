/**
 * An industry as the catalogue serialises it.
 *
 * Transcribed from `IndustryResource::toArray()`. There is deliberately no
 * label: the readable name of an industry is a translation, so the server sends
 * a stable `key` and this bundle owns the words for it under the `industries`
 * i18next namespace. A new language is a JSON file here, never a migration
 * there.
 */
export type Industry = {
    /** The uuid, and the value a form submits as `industry_id`. */
    id: string;
    /** The translation key, e.g. `barbershop`. */
    key: string;
    /** The catalogue's own order. Display order is decided per language. */
    position: number;
};
