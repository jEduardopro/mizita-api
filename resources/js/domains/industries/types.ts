/**
 * There is deliberately no label: the readable name of an industry is a
 * translation, so the server sends a stable `key` and this bundle owns the words
 * under the `industries` i18next namespace.
 */
export type Industry = {
    /** The uuid, and the value a form submits as `industry_id`. */
    id: string;
    /** The translation key, e.g. `barbershop`. */
    key: string;
    /** The catalogue's own order. Display order is decided per language. */
    position: number;
};
