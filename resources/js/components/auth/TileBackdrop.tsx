type Tone = 'muted' | 'surface' | 'brand-50' | 'brand-100' | 'brand-200' | 'brand-300';

type Shape = '1x1' | '2x1' | '1x2' | '2x2';

type Tile = {
    tone: Tone;
    shape?: Shape;
};

/**
 * The brand steps carry a `dark:` counterpart because the `brand-*` ramp is fixed
 * across themes — a `brand-50` tile would be a near-white slab on a dark page —
 * while `muted` and `surface-muted` already invert on their own.
 */
const toneClasses: Record<Tone, string> = {
    muted: 'bg-muted',
    surface: 'bg-surface-muted',
    'brand-50': 'bg-brand-50 dark:bg-brand-950/60',
    'brand-100': 'bg-brand-100 dark:bg-brand-900/55',
    'brand-200': 'bg-brand-200 dark:bg-brand-800/45',
    'brand-300': 'bg-brand-300/70 dark:bg-brand-700/45',
};

/**
 * A single-row tile states its height as a ratio, which is what sizes the
 * implicit grid rows: the columns are fractions of the viewport, so the row has
 * to be told it is as tall as one column is wide.
 */
const shapeClasses: Record<Shape, string> = {
    '1x1': 'aspect-square',
    '2x1': 'col-span-2 aspect-[2/1]',
    '1x2': 'row-span-2',
    '2x2': 'col-span-2 row-span-2',
};

/**
 * A constant and not a shuffle on purpose: a random mosaic is a different picture
 * on every reload, which cannot be reviewed or screenshotted. The order is
 * written against the widest grid, sixteen columns in bands of roughly one row,
 * and the tones alternate closely enough that no re-wrapping at a narrower
 * breakpoint produces a run of one colour. The tail covers a tall monitor.
 */
const tiles: readonly Tile[] = [
    { tone: 'brand-100', shape: '2x2' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-50', shape: '2x1' },
    { tone: 'brand-200' },
    { tone: 'surface' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-50' },
    { tone: 'brand-300' },

    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'muted', shape: '1x2' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },
    { tone: 'brand-200', shape: '2x1' },

    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200', shape: '2x2' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },

    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-50', shape: '2x1' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },

    { tone: 'brand-200' },
    { tone: 'brand-100', shape: '2x1' },
    { tone: 'brand-50' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'surface' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },

    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100', shape: '2x2' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'brand-50', shape: '2x1' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'surface' },

    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'muted' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },

    { tone: 'brand-100', shape: '2x1' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'surface' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-50' },
    { tone: 'brand-200', shape: '1x2' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'brand-300' },

    { tone: 'muted' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200', shape: '2x2' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'muted' },
    { tone: 'brand-200' },

    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'surface' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'brand-100', shape: '2x1' },
    { tone: 'muted' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },

    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-100' },

    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200', shape: '2x2' },
    { tone: 'brand-50' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },
    { tone: 'brand-200' },

    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100', shape: '2x1' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },

    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'surface' },
    { tone: 'brand-50', shape: '2x1' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'muted' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },

    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-100' },
];

type Layer = {
    focus: 'far' | 'near' | 'sharp';
    /** Empty for the pass that is in focus. */
    blur: string;
    /** The band of the window this pass is the one being seen. */
    mask: string;
};

/**
 * The wall is painted three times because CSS has no gradient of a filter: the
 * gradient has to be in what is revealed rather than in the filter itself.
 *
 * The stops overlap on purpose. The passes are the same opaque tiles in the same
 * places, so overlapping them changes nothing, while any gap between them would
 * show as a pale seam of bare background.
 */
const layers: readonly Layer[] = [
    {
        focus: 'far',
        blur: 'blur-2xl',
        mask: '[mask-image:linear-gradient(to_left,transparent_62%,black_88%,black_100%)]',
    },
    {
        focus: 'near',
        blur: 'blur-md',
        mask: '[mask-image:linear-gradient(to_left,transparent_24%,black_50%,black_70%,transparent_92%)]',
    },
    {
        focus: 'sharp',
        blur: '',
        mask: '[mask-image:linear-gradient(to_left,black_0%,black_26%,transparent_58%)]',
    },
];

/**
 * Pure decoration: it holds no text and nothing focusable, so it is hidden from
 * assistive technology at the root and ignores the pointer entirely.
 */
export function TileBackdrop() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none fixed inset-0 -z-10 overflow-hidden bg-background"
        >
            {layers.map((layer) => (
                <div key={layer.focus} className={`absolute inset-0 ${layer.mask}`}>
                    {/* Every pass is scaled by the same amount: a filter fades its
                        subject out towards the bounds of what it paints, so the
                        blurred passes need their edge pushed outside the window,
                        and an unscaled sharp pass would land its tiles elsewhere
                        and read as a double image. */}
                    <div className={`size-full scale-125 ${layer.blur}`}>
                        <TileGrid />
                    </div>
                </div>
            ))}

            {/* The veil tops out at a quarter of the background: anything heavier
                turns the left of the window into a flat wash and the blurred
                tiles stop being tiles. */}
            <div className="absolute inset-0 bg-gradient-to-l from-transparent via-background/10 to-background/24" />
        </div>
    );
}

/**
 * `grid-flow-dense` is structural, not cosmetic: a two-column tile that does not
 * fit in the columns left on a row would otherwise leave a hole, and the column
 * count changes four times down the breakpoints.
 *
 * Nothing in here moves, and that is a measurement rather than a preference. Two
 * of the three passes carry a filter, and a moving child forces the browser to
 * re-blur the whole window every frame — on this page that took 61fps down to 8.
 */
function TileGrid() {
    return (
        <div className="grid grid-flow-dense grid-cols-5 gap-2 p-2 sm:grid-cols-8 sm:gap-2.5 sm:p-2.5 lg:grid-cols-12 xl:grid-cols-16">
            {tiles.map((tile, index) => (
                <span
                    // A module constant that is never filtered, sorted or appended
                    // to, so the position in it is the tile's identity.
                    key={index}
                    className={`rounded-xl border border-brand-200/40 dark:border-brand-800/40 ${toneClasses[tile.tone]} ${shapeClasses[tile.shape ?? '1x1']}`}
                />
            ))}
        </div>
    );
}
