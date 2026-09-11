/** The paint a tile can take. Every one is an existing token in `app.css`. */
type Tone = 'muted' | 'surface' | 'brand-50' | 'brand-100' | 'brand-200' | 'brand-300';

/** How many columns and rows a tile covers. Four shapes, nothing else. */
type Shape = '1x1' | '2x1' | '1x2' | '2x2';

type Tile = {
    tone: Tone;
    /** Defaults to a single cell, which is what most of the mosaic is. */
    shape?: Shape;
};

/**
 * The ramp reads as four steps of blue on two broken whites, and it stops at
 * `brand-300`. Pure white is deliberately absent: the sheet resting on the wall
 * is the only fully white surface on the screen, and a mosaic that also carried
 * `bg-card` would blunt that edge wherever the two met.
 *
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
 * A single-row tile states its own height as a ratio, which is what sizes the
 * implicit grid rows: the columns are fractions of the viewport, so the row has
 * to be told it is as tall as one column is wide. A tile spanning two rows says
 * nothing about height and simply stretches across the two rows its neighbours
 * already sized.
 */
const shapeClasses: Record<Shape, string> = {
    '1x1': 'aspect-square',
    '2x1': 'col-span-2 aspect-[2/1]',
    '1x2': 'row-span-2',
    '2x2': 'col-span-2 row-span-2',
};

/**
 * The composition, declared tile by tile.
 *
 * It is a constant and not a shuffle on purpose: a random mosaic is a different
 * picture on every reload, which cannot be reviewed, cannot be screenshotted and
 * cannot be reasoned about when someone asks why a blue block landed behind the
 * sheet. Written out, the arrangement is a design decision like any other — blue
 * is the ground rather than the accent, it never touches its own step, and the
 * two broken whites are what keep it from reading as a flat field. It has to hold
 * at both ends of the window: on the right the tiles are seen in focus, where a
 * blue one is as loud as it will ever be, and on the left they are averaged into
 * each other by the blur.
 *
 * The order is written against the widest grid — sixteen columns, in bands of
 * roughly one row each. Narrower breakpoints re-wrap it, so the tones alternate
 * closely enough that no re-wrapping produces a run of one colour.
 *
 * `brand-300` is the one step that is placed by position rather than by rhythm:
 * it only appears late in a band, which lands it from the middle of the window
 * rightwards, where the mosaic is sharp and a saturated blue is an accent. Put
 * on the left it would survive the blur as a bruise behind the sheet. The
 * mapping is approximate once the grid re-wraps, which is why the step is rare.
 *
 * The list is longer than a 1440×900 window needs; the tail is what covers a
 * tall monitor, and the container clips whatever is left over.
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

/** One pass of the wall, and how much of the picture it is in focus for. */
type Layer = {
    /** Names the distance the layer stands at. Also its key. */
    focus: 'far' | 'near' | 'sharp';
    /** Empty for the pass that is in focus. */
    blur: string;
    /** The band of the window this pass is the one being seen. */
    mask: string;
};

/**
 * The wall is painted three times, each pass at a different focus, and each one
 * masked to the band of the window where it is the pass that shows.
 *
 * That is the only way to get a blur that changes across a picture: CSS has no
 * gradient of a filter, so the gradient has to be in what is revealed rather than
 * in the filter itself. Written right to left, the mosaic starts in focus where
 * there is nothing on top of it, softens through the middle, and is fully out of
 * focus behind the sheet — where it is still meant to be read as tiles, only
 * quietly, which is why the far pass stops at `blur-2xl` and the stops sit
 * further left than the blur alone would need.
 *
 * The stops overlap on purpose, and at every point across the window the three
 * masks add up to more than one — the passes are the same opaque tiles in the
 * same places, so overlapping them changes nothing, while any gap between them
 * would show as a pale seam of bare background.
 *
 * Listed back to front: the blurred pass is painted first and the sharp one lies
 * over it.
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
 * The mosaic behind the login screen: a wall of tiles in the pale steps of the
 * brand blue, broken up by two off-whites, in focus on one side of the window and
 * out of focus on the other.
 *
 * It is the whole background, not a panel beside the sheet — the sheet sits on
 * the wall rather than next to a picture, and the wall carries on around and
 * behind it. Pure decoration: it holds no text and nothing focusable, so it is
 * hidden from assistive technology at the root and ignores the pointer entirely.
 *
 * The focus is what makes the tiles a background rather than an interface. In
 * focus everywhere, a grid of rectangles is the shape of a loading skeleton and
 * gets read as one; out of focus everywhere it is a wash with no tiles left in
 * it. So it is sharp on the right, where nothing sits on top and the mosaic is
 * worth looking at, and softens towards the left until what is behind the sheet
 * is texture rather than pattern.
 */
export function TileBackdrop() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none fixed inset-0 -z-10 overflow-hidden bg-background"
        >
            {layers.map((layer) => (
                <div key={layer.focus} className={`absolute inset-0 ${layer.mask}`}>
                    {/*
                     * Every pass is scaled by the same amount, and that is the
                     * point: a filter fades its subject out towards the bounds of
                     * what it is painting, so the blurred passes need their edge
                     * pushed outside the window — and if the sharp pass were left
                     * unscaled its tiles would land somewhere else, and the
                     * transition would read as a double image instead of as one
                     * wall coming into focus.
                     */}
                    <div className={`size-full scale-125 ${layer.blur}`}>
                        <TileGrid />
                    </div>
                </div>
            ))}

            {/*
             * The veil runs the same way the focus does: nothing on the right,
             * where the tiles are meant to be seen, thickening to the left until
             * the sheet has a settled ground to stand on. It tops out at a
             * quarter of the background, and that ceiling is the whole point —
             * anything heavier turns the left of the window into a flat wash and
             * the blurred tiles stop being tiles. Separating the sheet from the
             * mosaic is the sheet's own job, through its border and its shadow.
             */}
            <div className="absolute inset-0 bg-gradient-to-l from-transparent via-background/10 to-background/24" />
        </div>
    );
}

/**
 * One pass of the tiles, at whatever focus the layer around it is holding.
 *
 * `grid-flow-dense` is structural, not cosmetic. A two-column tile that does not
 * fit in the columns left on a row would otherwise leave a hole, and the number
 * of columns changes four times down the breakpoints — dense packing is what
 * keeps the wall solid at every one of them instead of only at the width it was
 * drawn against.
 *
 * Nothing in here moves, and that is a measurement rather than a preference. The
 * eight tiles that used to drift were painted by all three passes, so they stayed
 * in phase and never doubled — but two of those passes carry a filter, and a
 * moving child forces the browser to re-blur the whole window every frame. On
 * this page that took 61fps down to 8. The drift was worth 8px; it is not worth
 * the frame, and at this density the arithmetic is worse, not better.
 */
function TileGrid() {
    return (
        <div className="grid grid-flow-dense grid-cols-5 gap-2 p-2 sm:grid-cols-8 sm:gap-2.5 sm:p-2.5 lg:grid-cols-12 xl:grid-cols-16">
            {tiles.map((tile, index) => (
                <span
                    // The list is a module constant that is never filtered, sorted
                    // or appended to, so the position in it is the tile's identity.
                    key={index}
                    className={`rounded-xl border border-brand-200/40 dark:border-brand-800/40 ${toneClasses[tile.tone]} ${shapeClasses[tile.shape ?? '1x1']}`}
                />
            ))}
        </div>
    );
}
