<?php

namespace App\Domain\Tools;

use App\Domain\Farm\Palette;
use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Jobs\ProcessModelFile;
use App\Models\AnonymousSession;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Made-to-measure products from a handful of numbers: organizer, box with a lid and openings, phone stand, cable holder.
 * Exact solids from engines/python/param_tool.py. Limits live here (web layer) and again in the tool itself.
 */
final class ParametricGenerator
{
    /**
     * Things cut out of a picture or a name (engines/python/shape_kinds.py): pendant, earrings, Christmas ornament, fridge
     * magnet, coaster. Each is a kind of its own (its own page, limits and stored designs) built by the one builder;
     * what they share is written once here and the kinds only say where they differ.
     */
    public const FAMILY = ['charm' => 'shape', 'keychain' => 'shape', 'earrings' => 'shape', 'ornament' => 'shape', 'magnet' => 'shape', 'coaster' => 'shape', 'gingerbread' => 'shape', 'name_letter' => 'shape', 'cookie' => 'shape', 'topper' => 'shape', 'tray' => 'shape', 'badge' => 'shape', 'medallion' => 'shape', 'photo_organizer' => 'shape', 'bag_charm' => 'shape', 'straw' => 'shape', 'opener' => 'shape'];

    private const SHAPE_FIELDS = [
        'width' => [20, 120, 45, 1], 'thickness' => [2, 6, 3, 0.2], 'frame' => [0, 3, 1.2, 0.1],
        // the picture: how many colours, how much of the background goes, how smooth the edges are, the photo's own sliders
        'colors_n' => [1, 8, 4, 1], 'bg_strength' => [0, 100, 30, 1], 'smooth' => [0, 1, 0.3, 0.05], 'contrast' => [50, 150, 100, 1], 'brightness' => [50, 150, 100, 1], 'saturation' => [0, 200, 100, 1],
        // every colour lies this much higher than the one under it: whole 0.2 mm layers, so a colour ends where a layer ends
        'relief' => [0.4, 1.2, 0.6, 0.2],
    ];

    private const SHAPE_EYELET = ['eye_pos' => [0, 100, 0, 0.5], 'eye_hole' => [2, 6, 3, 0.5], 'eye_wall' => [1.5, 3, 2, 0.1]];

    /** family → field, flag or choice → the section of the tool page it belongs to (fields and flags: size, choices: input, when not named) */
    public const PLACE = ['sign' => ['ring_at' => 'size'], 'shape' => [
        'bg_strength' => 'input', 'smooth' => 'input', 'contrast' => 'input', 'brightness' => 'input', 'saturation' => 'input', 'remove_bg' => 'input',
        'colors_n' => 'colors', 'relief' => 'colors', 'flush' => 'colors', 'rim' => 'colors', 'body' => 'size', 'mount' => 'size', 'disc' => 'size', 'floor' => 'size', 'inside' => 'size',
    ]];

    /** fields of the input section that sit folded under "adjust the photo" */
    public const FOLDED = ['contrast', 'brightness', 'saturation'];

    /** kind → the picture of our library a new visitor starts with (a tool that needs a picture must not open empty) */
    /**
     * The typefaces of the text tools: key (what a design stores as `typeface`) → [file, the family's name, group].
     * The first four are the ones the tools started with; their keys stay, designs made before carry them. Everything in
     * engines/fonts is under the SIL Open Font License, the file untouched and its licence next to it (<Family>-OFL.txt);
     * a family that comes as one variable file is read at its boldest (shape2d.text). Only faces that print: bold cuts,
     * the whole Czech and Spanish alphabet. The pictures of the picker: `php artisan matplace:font-previews`.
     */
    public const FONTS = [
        'sans' => ['vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf', 'DejaVu Sans', 'plain'],
        'serif' => ['vendor/dompdf/dompdf/lib/fonts/DejaVuSerif-Bold.ttf', 'DejaVu Serif', 'serif'],
        'mono' => ['vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono-Bold.ttf', 'DejaVu Mono', 'tech'],
        'script' => ['engines/fonts/Pacifico-Regular.ttf', 'Pacifico', 'hand'],
        'montserrat' => ['engines/fonts/Montserrat-wght.ttf', 'Montserrat', 'plain'],
        'oswald' => ['engines/fonts/Oswald-wght.ttf', 'Oswald', 'plain'],
        'bebas' => ['engines/fonts/BebasNeue-Regular.ttf', 'Bebas Neue', 'plain'],
        'anton' => ['engines/fonts/Anton-Regular.ttf', 'Anton', 'plain'],
        'archivo' => ['engines/fonts/ArchivoBlack-Regular.ttf', 'Archivo Black', 'plain'],
        'russo' => ['engines/fonts/RussoOne-Regular.ttf', 'Russo One', 'plain'],
        'comfortaa' => ['engines/fonts/Comfortaa-wght.ttf', 'Comfortaa', 'plain'],
        'playfair' => ['engines/fonts/PlayfairDisplay-wght.ttf', 'Playfair Display', 'serif'],
        'alfa_slab' => ['engines/fonts/AlfaSlabOne-Regular.ttf', 'Alfa Slab One', 'serif'],
        'abril' => ['engines/fonts/AbrilFatface-Regular.ttf', 'Abril Fatface', 'serif'],
        'lobster' => ['engines/fonts/Lobster-Regular.ttf', 'Lobster', 'hand'],
        'caveat' => ['engines/fonts/Caveat-wght.ttf', 'Caveat', 'hand'],
        'dancing' => ['engines/fonts/DancingScript-wght.ttf', 'Dancing Script', 'hand'],
        'great_vibes' => ['engines/fonts/GreatVibes-Regular.ttf', 'Great Vibes', 'hand'],
        'sacramento' => ['engines/fonts/Sacramento-Regular.ttf', 'Sacramento', 'hand'],
        'kaushan' => ['engines/fonts/KaushanScript-Regular.ttf', 'Kaushan Script', 'hand'],
        'courgette' => ['engines/fonts/Courgette-Regular.ttf', 'Courgette', 'hand'],
        'patrick_hand' => ['engines/fonts/PatrickHand-Regular.ttf', 'Patrick Hand', 'hand'],
        'amatic' => ['engines/fonts/AmaticSC-Bold.ttf', 'Amatic SC', 'hand'],
        'bangers' => ['engines/fonts/Bangers-Regular.ttf', 'Bangers', 'fun'],
        'titan' => ['engines/fonts/TitanOne-Regular.ttf', 'Titan One', 'fun'],
        'paytone' => ['engines/fonts/PaytoneOne-Regular.ttf', 'Paytone One', 'fun'],
        'bungee' => ['engines/fonts/Bungee-Regular.ttf', 'Bungee', 'fun'],
        'righteous' => ['engines/fonts/Righteous-Regular.ttf', 'Righteous', 'fun'],
        'baloo' => ['engines/fonts/Baloo2-wght.ttf', 'Baloo 2', 'fun'],
        'press_start' => ['engines/fonts/PressStart2P-Regular.ttf', 'Press Start 2P', 'tech'],
    ];

    /** The composer: how many layers a design may have, and the shapes a layer can be (three plain ones and the plates of engines/shapes). */
    public const MAX_LAYERS = 12;

    public const LAYER_SHAPES = ['rounded', 'rect', 'circle', 'heart', 'star', 'cloud', 'bone', 'hexagon', 'banner', 'arrow', 'house', 'car', 'cat', 'candy', 'flower', 'shield', 'tag', 'bubble', 'fish'];

    /** Tools that keep to the few typefaces of their own: a letter on a bead of 8 mm has to be plain. */
    public const OWN_FACES = ['beads'];

    /**
     * What a tool lets the visitor choose from: its own choices, and for the typeface every face of the registry. The
     * faces the tool names itself come first, the very first one is what it opens with.
     *
     * @return array<string, list<string>>
     */
    public static function choicesOf(string $kind): array
    {
        $choices = self::CHOICES[$kind] ?? [];
        if (isset($choices['typeface']) && ! in_array($kind, self::OWN_FACES, true)) {
            $choices['typeface'] = array_values(array_unique([...$choices['typeface'], ...array_keys(self::FONTS)]));
        }

        return $choices;
    }

    public static function fontPath(?string $typeface, string $fallback = 'sans'): string
    {
        return base_path((self::FONTS[$typeface ?? ''] ?? self::FONTS[$fallback])[0]);
    }

    /** Tools of the family whose text goes under the picture, not instead of it: a page opened with a name keeps its sample picture. */
    public const CAPTIONED = ['badge'];

    public const SAMPLE = ['papel' => 'lib:holidays/sugar-skull', 'notes' => 'lib:animals/cat', 'hair_tie' => 'lib:hearts-stars/crown', 'candle_stand' => 'lib:holidays/christmas-tree', 'svg_to_stl' => 'lib:animals/cat', 'nameplate' => 'lib:hearts-stars/star', 'charm' => 'lib:colour/happy-ghost', 'earrings' => 'lib:colour/red-heart', 'ornament' => 'lib:colour/gingerbread-man', 'magnet' => 'lib:colour/paw-badge', 'coaster' => 'lib:colour/snowman', 'cookie' => 'lib:colour/gingerbread-man', 'tray' => 'lib:colour/paw-badge', 'badge' => 'lib:colour/smiling-star', 'medallion' => 'lib:colour/smiling-star', 'photo_organizer' => 'lib:nature/cloud', 'bag_charm' => 'lib:colour/red-heart', 'straw' => 'lib:colour/smiling-star', 'opener' => 'lib:colour/paw-badge'];

    /** kind → field → [min, max, default, step]; integers have step 1 */
    public const FIELDS = [
        // a pen holder in the shape of a name (engines/python/name_kinds.py): printed standing, the name itself is the cup
        'name_cup' => ['width' => [80, 250, 160, 1], 'height' => [40, 120, 80, 1], 'wall' => [1.2, 3, 1.6, 0.2], 'floor' => [1.2, 4, 2, 0.2]],
        // a bead for every character of a text; `size` is the edge of a cube bead, the other shapes follow it
        'beads' => ['size' => [8, 14, 10, 1], 'hole' => [1.5, 4, 2.5, 0.5], 'relief' => [0.4, 1.2, 0.6, 0.2]],
        'charm' => self::SHAPE_FIELDS + self::SHAPE_EYELET,
        // the eyelet starts on the left side (three quarters of the way round), where a key ring pulls along the name
        'keychain' => ['width' => [30, 100, 55, 1], 'thickness' => [2.4, 6, 3, 0.2], 'eye_pos' => [0, 100, 75, 0.5], 'eye_hole' => [3, 8, 5, 0.5]] + self::SHAPE_FIELDS + self::SHAPE_EYELET,
        'earrings' => ['width' => [10, 60, 32, 1], 'thickness' => [1.6, 3, 2.4, 0.2], 'frame' => [0, 2, 0.8, 0.1], 'relief' => [0.2, 0.8, 0.4, 0.2], 'eye_hole' => [1.5, 3, 2, 0.1], 'eye_wall' => [1.2, 3, 1.6, 0.1]] + self::SHAPE_FIELDS + self::SHAPE_EYELET,
        'ornament' => ['width' => [40, 150, 80, 1], 'thickness' => [2, 5, 3, 0.2], 'eye_hole' => [3, 6, 4, 0.5]] + self::SHAPE_FIELDS + self::SHAPE_EYELET,
        'magnet' => ['width' => [40, 100, 60, 1], 'thickness' => [3, 6, 3, 0.2], 'frame' => [0, 3, 1.5, 0.1]] + self::SHAPE_FIELDS + ['mag_d' => [4, 30, 10, 0.5], 'mag_h' => [1, 6, 2, 0.5], 'mag_gap' => [0.1, 0.3, 0.2, 0.05]],
        'coaster' => ['width' => [80, 120, 100, 1], 'thickness' => [3, 6, 4, 0.2], 'frame' => [0, 4, 2, 0.1], 'relief' => [0.2, 0.8, 0.4, 0.2]] + self::SHAPE_FIELDS,
        // the shape is ours (a gingerbread man, a heart, a star, a tree), the visitor brings the name: no picture, no picture's fields
        'gingerbread' => ['width' => [50, 150, 90, 1], 'thickness' => [2, 6, 3, 0.2], 'relief' => [0.4, 1.2, 0.6, 0.2], 'eye_hole' => [3, 6, 4, 0.5]] + self::SHAPE_EYELET,
        // a medal: the eyelet takes a link of the chain (a bar of 4 mm), `links` is how many of them are printed next to it
        'medallion' => ['width' => [50, 120, 80, 1], 'thickness' => [3, 6, 4, 0.2], 'frame' => [0, 5, 2, 0.1]] + self::SHAPE_FIELDS + ['eye_pos' => [0, 100, 0, 0.5], 'eye_hole' => [5, 8, 6, 0.5], 'eye_wall' => [2, 4, 3, 0.1], 'links' => [0, 40, 20, 1]],
        // a charm for a bag with holes: `bag_hole` is the hole of the bag the pin goes through, `bag_wall` how thick the bag is there
        'bag_charm' => ['width' => [30, 80, 45, 1], 'thickness' => [4, 8, 5, 0.2], 'frame' => [0, 3, 1.5, 0.1]] + self::SHAPE_FIELDS + ['bag_hole' => [6, 20, 12, 0.5], 'bag_wall' => [1, 10, 4, 0.5]],
        // held, not hung: a clip for a drinking straw of `straw_d`, and the tongue of a can opener; both sit on the outline at `eye_pos`
        'straw' => ['width' => [20, 60, 35, 1], 'thickness' => [2, 5, 3, 0.2], 'frame' => [0, 3, 1.5, 0.1]] + self::SHAPE_FIELDS + ['eye_pos' => [0, 100, 25, 0.5], 'straw_d' => [5, 14, 8, 0.5]],
        'opener' => ['width' => [35, 90, 55, 1], 'thickness' => [3, 6, 4, 0.2], 'frame' => [0, 3, 1.5, 0.1]] + self::SHAPE_FIELDS + ['eye_pos' => [0, 100, 0, 0.5]],
        // a topper glued on a badge reel: `mag_d` × `mag_h` is the pocket in its back for the reel's sticky dot
        'badge' => ['width' => [25, 60, 40, 1], 'thickness' => [2, 5, 3, 0.2], 'frame' => [0, 3, 1.5, 0.1]] + self::SHAPE_FIELDS + ['mag_d' => [10, 30, 19, 0.5], 'mag_h' => [0.4, 2, 0.8, 0.2]],
        // a tall dish in the shape of a picture with compartments: `cell` is the size of one in a grid, `hole_d` a round hole for a toothbrush
        'photo_organizer' => ['width' => [60, 200, 120, 1], 'height' => [40, 120, 80, 1], 'thickness' => [1.2, 4, 2, 0.2], 'wall' => [1.2, 3, 1.6, 0.2], 'cell' => [20, 80, 40, 1], 'hole_d' => [8, 40, 20, 1]] + self::SHAPE_FIELDS,
        // a little dish in the shape of a picture: `thickness` is its floor, `height` its rim, `frame` how far the picture keeps off the wall
        'tray' => ['width' => [50, 200, 100, 1], 'height' => [8, 40, 15, 1], 'thickness' => [1.2, 4, 2, 0.2], 'frame' => [0, 10, 3, 0.5], 'wall' => [1.2, 3, 1.6, 0.2]] + self::SHAPE_FIELDS,
        // a biscuit to play with or to hang: a picture or a silhouette as dough, icing drawn on it by hand (`strokes`)
        'cookie' => ['width' => [40, 150, 80, 1], 'thickness' => [4, 10, 6, 0.5], 'frame' => [0, 6, 2, 0.1], 'eye_hole' => [3, 6, 4, 0.5]] + self::SHAPE_FIELDS + self::SHAPE_EYELET,
        // a number or a shape with a name across it and sticks under it: the name's width in % of the width, its place in % of the shape's height
        'topper' => ['width' => [50, 200, 110, 1], 'text_size' => [30, 150, 105, 1], 'text_y' => [-60, 60, -12, 1], 'spike' => [30, 100, 60, 1], 'spikes' => [1, 2, 2, 1],
            'thickness' => [2, 4, 3, 0.2], 'relief' => [0.4, 1.2, 0.8, 0.2]],
        // a big first letter with the whole name on it: thick enough to stand on a shelf
        'name_letter' => ['height' => [60, 200, 120, 1], 'thickness' => [3, 15, 8, 0.2], 'relief' => [0.4, 2, 1, 0.2], 'eye_hole' => [3, 8, 5, 0.5]] + self::SHAPE_EYELET,
        'organizer' => [
            'width' => [30, 400, 200, 1], 'depth' => [30, 400, 120, 1], 'height' => [10, 150, 40, 1],
            'rows' => [1, 8, 2, 1], 'cols' => [1, 8, 3, 1], 'radius' => [0, 20, 4, 0.5], 'wall' => [0.8, 4, 1.6, 0.2], 'floor' => [0.8, 4, 1.2, 0.2],
        ],
        'box' => [
            'inner_w' => [10, 300, 80, 1], 'inner_d' => [10, 300, 50, 1], 'inner_h' => [8, 200, 30, 1],
            'wall' => [1.2, 5, 2, 0.2], 'floor' => [1, 5, 1.6, 0.2], 'clearance' => [0.1, 0.6, 0.25, 0.05], 'radius' => [0, 30, 2.5, 0.5],
            'cable_d' => [3, 30, 8, 1],
        ],
        'phone_stand' => [
            'width' => [50, 260, 70, 1], 'device' => [7, 20, 12, 1], 'angle' => [35, 80, 65, 1], 'back' => [60, 200, 100, 1], 'thickness' => [3, 8, 5, 0.5], 'radius' => [0, 4, 2, 0.1], 'depth' => [40, 120, 60, 1], 'vent' => [1, 4, 1.5, 0.1],
        ],
        'cable_holder' => [
            'count' => [1, 8, 4, 1], 'cable' => [3, 14, 6, 0.5], 'depth' => [10, 80, 45, 1], 'wall' => [2, 12, 7, 0.5], 'radius' => [0, 6, 3, 0.5],
        ],
        'modular' => [
            'inner_w' => [60, 600, 300, 1], 'inner_d' => [60, 600, 150, 1], 'height' => [15, 120, 40, 1], 'cols' => [1, 12, 6, 1], 'rows' => [1, 12, 3, 1],
            'radius' => [0, 15, 6, 0.5], 'wall' => [0.8, 3, 1.6, 0.2], 'floor' => [0.8, 3, 1.2, 0.2], 'gap' => [0.3, 1.5, 0.6, 0.1],
        ],
        'vase' => [
            'height' => [40, 300, 180, 1], 'top_d' => [30, 250, 62, 1], 'bottom_d' => [30, 250, 54, 1], 'wall' => [0.8, 4, 1.6, 0.2], 'floor' => [0.8, 5, 1.6, 0.2],
            'ribs' => [6, 48, 20, 1], 'flute' => [0, 45, 20, 1], 'twist' => [0, 360, 200, 1],
        ],
        'sign' => ['text_height' => [4, 80, 12, 1], 'thickness' => [1.2, 30, 3, 0.2], 'relief' => [0.4, 5, 1.2, 0.2], 'margin' => [2, 30, 5, 1], 'radius' => [0, 30, 6, 0.5]],
        'logo' => ['width' => [20, 250, 80, 1], 'thickness' => [0.6, 50, 2, 0.2], 'plate' => [0.8, 6, 2, 0.2], 'margin' => [0, 20, 5, 1], 'base_h' => [8, 40, 11, 1]],
        'stamp' => ['width' => [15, 120, 50, 1], 'relief' => [0.8, 4, 1.6, 0.2], 'plate' => [2, 6, 3, 0.5]],
        'qr' => ['size' => [30, 150, 70, 1], 'plate' => [1.6, 4, 2.4, 0.2], 'relief' => [0.6, 2, 1, 0.2]],
        'stencil' => ['width' => [30, 250, 120, 1], 'thickness' => [0.8, 3, 1.2, 0.2], 'margin' => [5, 40, 12, 1], 'bridge' => [0.8, 3, 1.2, 0.2]],
        // layers laid one on another by hand (engines/python/compose_kind.py): `thickness` is the lowest layer's, `step` what every next one adds
        'compose' => ['thickness' => [1.2, 8, 3, 0.2], 'step' => [0.4, 1.6, 0.8, 0.2], 'eye_hole' => [2, 8, 4, 0.5], 'spike' => [30, 100, 60, 1]],
        // a drawer insert from a photo of things on a sheet of A4 (engines/python/sheet_kinds.py): pockets `depth` deep with `gap` of play, the tray `margin` wider than the things
        'insert' => ['depth' => [4, 40, 15, 1], 'gap' => [0.3, 3, 1.5, 0.1], 'margin' => [2, 15, 5, 1], 'floor' => [1.2, 4, 1.6, 0.2]],
        // a figure standing behind a tray for sticky notes (engines/python/stand_kinds.py): `width` and `thickness` are the figure's, `pad` the side of the pad, `depth` how deep it lies
        'notes' => ['width' => [50, 150, 90, 1], 'thickness' => [2.4, 5, 3, 0.2], 'pad' => [50, 105, 76, 1], 'depth' => [8, 30, 12, 1]],
        // a figure standing behind a platform for a candle in a jar of `jar_d`
        'candle_stand' => ['width' => [50, 160, 100, 1], 'thickness' => [2.4, 5, 3, 0.2], 'jar_d' => [40, 130, 80, 1]],
        // a figure standing behind a post for hair ties: `post_d` and `post_h` are the post's
        'hair_tie' => ['width' => [50, 150, 90, 1], 'thickness' => [2.4, 5, 3, 0.2], 'post_d' => [10, 30, 14, 1], 'post_h' => [40, 150, 90, 1]],
        // papel picado: `darkness` is where a photo is split into paper and holes (50 = where the picture splits itself), `soften` how much detail is given up
        'papel' => ['width' => [80, 250, 150, 1], 'height' => [80, 250, 200, 1], 'thickness' => [0.8, 2, 1.2, 0.2], 'bridge' => [0.8, 2.4, 1.2, 0.2], 'darkness' => [10, 90, 50, 1], 'soften' => [0, 3, 1, 0.5]],
        'lightbox' => [
            'width' => [80, 300, 180, 1], 'depth' => [25, 80, 35, 1], 'wall' => [1.6, 4, 2, 0.2], 'face' => [0.8, 2, 1.2, 0.2], 'margin' => [6, 40, 12, 1],
            'bridge' => [0.8, 3, 1.4, 0.2], 'cable' => [3, 10, 5, 0.5], 'clearance' => [0.1, 0.6, 0.25, 0.05],
        ],
        'holder' => ['obj_w' => [10, 300, 50, 1], 'obj_d' => [5, 150, 25, 1], 'height' => [15, 150, 60, 1], 'hook_h' => [10, 150, 30, 1], 'bend' => [0, 40, 6, 0.5], 'edge' => [0, 2, 1, 0.1],
            'wall' => [2, 6, 3, 0.5], 'clearance' => [0.3, 2, 0.8, 0.1], 'radius' => [0, 12, 1.5, 0.5]],
        'cap' => ['size_a' => [5, 200, 40, 0.1], 'size_b' => [8, 200, 30, 0.1], 'height' => [4, 60, 12, 1], 'wall' => [1.2, 4, 2, 0.2], 'top' => [1.2, 5, 2, 0.2], 'clearance' => [0.1, 1, 0.3, 0.05], 'pitch' => [0.5, 6, 3, 0.05], 'edge' => [0, 3, 1, 0.5], 'mouth' => [4, 195, 21.7, 0.1], 'outer' => [0, 220, 0, 1]],
        'cutter' => ['width' => [30, 150, 70, 1], 'height' => [10, 30, 18, 1], 'wall' => [0.8, 1.6, 1.0, 0.2], 'flange' => [3, 10, 5, 1], 'flange_t' => [1, 2.5, 1.6, 0.1]],
    ];

    /** kind → choice → allowed values (the first one is the default) */
    public const CHOICES = [
        'compose' => ['base' => ['none', 'eyelet', 'sticks']],
        'notes' => ['typeface' => ['sans', 'serif', 'mono', 'script']], 'hair_tie' => ['typeface' => ['script', 'sans', 'serif', 'mono']], 'candle_stand' => ['typeface' => ['script', 'sans', 'serif', 'mono']],
        'papel' => ['border' => ['flowers', 'diamonds', 'dots', 'hearts', 'leaves', 'stars', 'none']],
        'name_cup' => ['typeface' => ['script', 'sans', 'serif', 'mono']],
        'beads' => ['shape' => ['cube', 'ball', 'heart', 'star'], 'style' => ['raised', 'engraved'], 'typeface' => ['sans', 'serif', 'mono']],
        'charm' => ['body' => ['image', 'circle', 'rect'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'keychain' => ['body' => ['rect', 'image', 'circle'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'earrings' => ['body' => ['image', 'circle'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'ornament' => ['body' => ['image', 'circle', 'star'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'magnet' => ['body' => ['image', 'circle', 'rect'], 'typeface' => ['sans', 'serif', 'mono', 'script'], 'mount' => ['glue', 'press', 'through', 'none'],
            'disc' => ['custom', 'd6x2', 'd8x3', 'd10x2', 'd12x3', 'd15x3', 'd20x3']],
        'coaster' => ['body' => ['circle', 'square', 'hex'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'gingerbread' => ['cookie' => ['man', 'heart', 'star', 'tree'], 'icing' => ['wavy', 'plain', 'none'], 'typeface' => ['script', 'sans', 'serif', 'mono']],
        'name_letter' => ['letter_face' => ['sans', 'serif', 'mono'], 'typeface' => ['script', 'sans', 'serif', 'mono']],
        'cookie' => ['body' => ['image', 'circle'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'topper' => ['template' => ['number', 'heart', 'star', 'circle', 'none'], 'typeface' => ['script', 'sans', 'serif', 'mono']],
        'tray' => ['body' => ['image', 'circle'], 'floor' => ['engraved', 'colors', 'plain'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'badge' => ['body' => ['image', 'circle', 'rect'], 'typeface' => ['sans', 'serif', 'mono', 'script'], 'mount' => ['glue', 'none']],
        'medallion' => ['body' => ['circle', 'star', 'hex'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'photo_organizer' => ['body' => ['image', 'circle'], 'inside' => ['grid', 'holes', 'open']],
        'bag_charm' => ['body' => ['image', 'circle', 'rect'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'straw' => ['body' => ['image', 'circle'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'opener' => ['body' => ['image', 'circle', 'rect'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'phone_stand' => ['style' => ['plate', 'wave', 'desk', 'wedge', 'wall', 'car']],
        'vase' => ['purpose' => ['vase', 'pot'], 'profile' => ['neck', 'belly', 'cone', 'tulip'], 'style' => ['twist', 'ribs', 'smooth']],
        // `shape`: a box, an oval, or a plate drawn as an outline (engines/shapes/<name>.svg); `motif_at`: where the picture stands against the text
        'sign' => ['style' => ['emboss', 'engrave', 'outline', 'name', 'stand'], 'shape' => ['rounded', 'rect', 'oval', 'heart', 'star', 'cloud', 'bone', 'hexagon', 'banner', 'arrow', 'house', 'car', 'cat', 'candy', 'flower', 'shield', 'tag', 'bubble', 'circle', 'fish'],
            'typeface' => ['sans', 'serif', 'mono', 'script'], 'motif_at' => ['left', 'right', 'above'], 'ring_at' => ['left', 'right', 'top']],
        'logo' => ['mode' => ['relief', 'height', 'cutout', 'standing'], 'shape' => ['rounded', 'rect', 'circle']],
        'stamp' => ['mode' => ['raised', 'recessed'], 'handle' => ['knob', 'none']],
        // a code is read only in two colours: the plate (light ones first) and the code with its caption (dark ones first)
        'qr' => ['plate_color' => ['white', 'yellow', 'grey', 'brown', 'orange', 'red', 'green', 'blue', 'black'], 'code_color' => ['black', 'blue', 'green', 'red', 'brown', 'orange', 'grey', 'yellow', 'white']],
        'lightbox' => ['shape' => ['rect', 'round'], 'led' => ['strip8', 'strip10', 'module']],
        'cutter' => ['edge' => ['sharp', 'straight'], 'typeface' => ['sans', 'serif', 'mono', 'script']],
        'holder' => ['style' => ['cradle', 'pocket', 'hook', 'clip'], 'holes' => ['round', 'keyhole']],
        'cap' => ['style' => ['push', 'plug', 'thread'], 'shape' => ['round', 'rect', 'hex'], 'head' => ['flat', 'dome'], 'seal' => ['none', 'lip', 'liner'],
            'thread' => ['custom', 'pet28', 'm6', 'm8', 'm10', 'm12', 'm14', 'm16', 'm20', 'm24', 'm30']],
    ];

    /** kind → text input → [max length, required, default] */
    public const TEXTS = [
        'name_cup' => ['line1' => [14, true, 'Jana']],
        'beads' => ['line1' => [16, true, 'JANA']],
        'charm' => ['line1' => [24, false, ''], 'line2' => [24, false, '']], 'keychain' => ['line1' => [24, false, 'Jana'], 'line2' => [24, false, '']], 'earrings' => ['line1' => [12, false, ''], 'line2' => [12, false, '']],
        'ornament' => ['line1' => [24, false, ''], 'line2' => [24, false, '']], 'magnet' => ['line1' => [24, false, ''], 'line2' => [24, false, '']],
        'coaster' => ['line1' => [24, false, ''], 'line2' => [24, false, '']],
        'gingerbread' => ['line1' => [16, false, 'Ela']],
        'name_letter' => ['line1' => [20, true, 'Ela'], 'initial' => [1, false, '']],
        'cookie' => ['line1' => [16, false, ''], 'line2' => [16, false, '']],
        'topper' => ['line1' => [20, false, 'Olivia'], 'number' => [3, false, '2']],
        'tray' => ['line1' => [16, false, ''], 'line2' => [16, false, '']],
        'badge' => ['line1' => [16, false, '']],
        'medallion' => ['line1' => [16, false, ''], 'line2' => [16, false, '']],
        'bag_charm' => ['line1' => [16, false, ''], 'line2' => [16, false, '']],
        'straw' => ['line1' => [12, false, ''], 'line2' => [12, false, '']], 'opener' => ['line1' => [16, false, ''], 'line2' => [16, false, '']],
        'sign' => ['line1' => [40, true, 'Jana'], 'line2' => [40, false, ''], 'line3' => [40, false, '']],
        'logo' => ['line1' => [30, false, 'LOGO'], 'line2' => [30, false, '']],
        'stamp' => ['line1' => [20, false, 'EVA'], 'line2' => [20, false, '']],
        'qr' => ['url' => [300, true, 'https://matplace.com'], 'label' => [40, false, 'matplace.com']],
        'stencil' => ['line1' => [24, false, 'BOA 8'], 'line2' => [24, false, '']],
        'notes' => ['line1' => [16, false, '']], 'hair_tie' => ['line1' => [16, false, '']], 'candle_stand' => ['line1' => [16, false, '']],
        'lightbox' => ['line1' => [16, false, 'OPEN'], 'line2' => [16, false, '']],
        'cutter' => ['line1' => [20, false, 'Ela'], 'line2' => [20, false, '']],
    ];

    /** kinds that accept an uploaded SVG or picture instead of text */
    /** Of the tools that take a picture: those where it stands next to the text, never instead of it (the text stays required). */
    public const BESIDE = ['sign'];

    public const ARTWORK = ['sign', 'logo', 'stamp', 'stencil', 'papel', 'notes', 'hair_tie', 'candle_stand', 'insert', 'lightbox', 'cutter', 'charm', 'keychain', 'earrings', 'ornament', 'magnet', 'coaster', 'cookie', 'tray', 'badge', 'medallion', 'photo_organizer', 'bag_charm', 'straw', 'opener'];

    /** the fields shown first; everything else sits under "more" */
    public const MAIN = [
        'name_cup' => ['width', 'height'], 'beads' => ['size', 'hole'],
        'charm' => ['width', 'thickness', 'frame', 'eye_pos', 'eye_hole'], 'keychain' => ['width', 'thickness', 'frame', 'eye_pos', 'eye_hole'], 'earrings' => ['width', 'thickness', 'frame', 'eye_pos', 'eye_hole'], 'ornament' => ['width', 'thickness', 'frame', 'eye_pos', 'eye_hole'],
        'magnet' => ['width', 'thickness', 'frame', 'mag_d', 'mag_h'], 'coaster' => ['width', 'thickness', 'frame'], 'gingerbread' => ['width', 'thickness', 'eye_pos', 'eye_hole'], 'name_letter' => ['height', 'thickness'], 'cookie' => ['width', 'thickness', 'frame'], 'topper' => ['width', 'text_size', 'text_y', 'spike', 'spikes'], 'tray' => ['width', 'height', 'frame'], 'badge' => ['width', 'thickness', 'frame', 'mag_d'], 'medallion' => ['width', 'thickness', 'frame', 'links'], 'photo_organizer' => ['width', 'height', 'cell', 'hole_d'], 'bag_charm' => ['width', 'thickness', 'bag_hole', 'bag_wall'], 'straw' => ['width', 'thickness', 'straw_d', 'eye_pos'], 'opener' => ['width', 'thickness', 'eye_pos'],
        'organizer' => ['width', 'depth', 'height', 'rows', 'cols', 'radius'], 'box' => ['inner_w', 'inner_d', 'inner_h', 'radius'], 'phone_stand' => ['width', 'device', 'angle', 'back', 'depth', 'vent', 'thickness', 'radius'],
        'cable_holder' => ['count', 'cable', 'depth'], 'modular' => ['inner_w', 'inner_d', 'height', 'cols', 'rows', 'radius'], 'vase' => ['height', 'top_d', 'bottom_d', 'ribs', 'flute', 'twist'], 'sign' => ['text_height', 'thickness', 'relief', 'radius'], 'logo' => ['width', 'thickness', 'base_h'], 'stamp' => ['width', 'relief'], 'qr' => ['size'], 'stencil' => ['width', 'margin'], 'papel' => ['width', 'height', 'darkness', 'soften'], 'notes' => ['width', 'pad', 'depth', 'thickness'], 'hair_tie' => ['width', 'post_h', 'post_d', 'thickness'], 'candle_stand' => ['width', 'jar_d', 'thickness'], 'insert' => ['depth', 'gap', 'margin'], 'compose' => ['thickness', 'step'], 'lightbox' => ['width', 'depth'], 'cutter' => ['width', 'height', 'wall', 'flange'], 'holder' => ['obj_w', 'obj_d', 'height', 'hook_h', 'bend', 'edge'], 'cap' => ['size_a', 'size_b', 'outer', 'height', 'pitch', 'mouth', 'edge'],
    ];

    /**
     * kind → size → the wall of the previewed model it moves: x (width, the right wall), y (depth, the back wall),
     * z (height, the top). The tool page puts an arrow there; dragging it changes the field. A size that grows to
     * both sides (the model is centred) counts the dragged distance twice, one that grows from the floor once.
     */
    public const HANDLES = [
        'organizer' => ['width' => 'x', 'depth' => 'y', 'height' => 'z'], 'box' => ['inner_w' => 'x', 'inner_d' => 'y', 'inner_h' => 'z'],
        'modular' => ['inner_w' => 'x', 'inner_d' => 'y', 'height' => 'z'], 'vase' => ['top_d' => 'x', 'height' => 'z'], 'phone_stand' => ['width' => 'x'],
        'cable_holder' => ['depth' => 'y'], 'holder' => ['obj_w' => 'x', 'height' => 'z'], 'cap' => ['size_a' => 'x', 'height' => 'z'],
        'logo' => ['width' => 'x'], 'stamp' => ['width' => 'x'], 'qr' => ['size' => 'x'], 'stencil' => ['width' => 'x'], 'papel' => ['width' => 'x', 'height' => 'y'], 'notes' => ['width' => 'x'], 'hair_tie' => ['width' => 'x'], 'candle_stand' => ['width' => 'x'], 'lightbox' => ['width' => 'x', 'depth' => 'z'],
        'name_cup' => ['width' => 'x', 'height' => 'z'], 'charm' => ['width' => 'x'], 'keychain' => ['width' => 'x'], 'ornament' => ['width' => 'x'], 'magnet' => ['width' => 'x'], 'coaster' => ['width' => 'x'], 'gingerbread' => ['width' => 'x'], 'name_letter' => ['height' => 'y'], 'cookie' => ['width' => 'x'], 'topper' => ['width' => 'x'], 'tray' => ['width' => 'x', 'height' => 'z'], 'badge' => ['width' => 'x'], 'medallion' => ['width' => 'x'], 'photo_organizer' => ['width' => 'x', 'height' => 'z'], 'bag_charm' => ['width' => 'x'], 'straw' => ['width' => 'x'], 'opener' => ['width' => 'x'],
        'cutter' => ['width' => 'x', 'height' => 'z'],
    ];

    public const PARTS = ['all', 'body', 'lid', 'saucer', 'handle', 'stand', 'imprint', 'cut', 'face', 'diffuser', 'back', 'plate', 'text', 'stamp',
        'rim', 'color_1', 'color_2', 'color_3', 'color_4', 'color_5', 'color_6', 'color_7', 'color_8', 'icing_1', 'icing_2', 'icing_3', 'icing_4', 'icing_5', 'icing_6',
        'layer_1', 'layer_2', 'layer_3', 'layer_4', 'layer_5', 'layer_6', 'layer_7', 'layer_8', 'layer_9', 'layer_10', 'layer_11', 'layer_12'];

    /** A biscuit's icing: how many strokes a drawing may have and how many points a stroke */
    public const MAX_STROKES = 60;

    public const MAX_STROKE_POINTS = 48;

    public const FLAGS = ['name_cup' => ['base'], 'beads' => ['two_sides'], 'charm' => ['remove_bg', 'eyelet', 'flush', 'rim', 'bevel'], 'keychain' => ['remove_bg', 'eyelet', 'flush', 'rim', 'bevel'], 'earrings' => ['remove_bg', 'eyelet', 'mirror', 'flush', 'rim', 'bevel'], 'ornament' => ['remove_bg', 'eyelet', 'flush', 'rim', 'bevel'],
        'magnet' => ['remove_bg', 'flush', 'rim', 'bevel'], 'coaster' => ['remove_bg', 'grooves', 'flush', 'rim', 'bevel'], 'gingerbread' => ['eyelet', 'flush', 'bevel'], 'name_letter' => ['hang', 'stand', 'flush', 'bevel'], 'cookie' => ['remove_bg', 'hang', 'tray', 'flush'], 'topper' => ['flush'], 'tray' => ['remove_bg'], 'badge' => ['remove_bg', 'flush', 'rim', 'bevel'], 'medallion' => ['remove_bg', 'eyelet', 'flush', 'rim', 'bevel'], 'photo_organizer' => ['remove_bg'], 'bag_charm' => ['remove_bg', 'flush', 'rim', 'bevel'], 'straw' => ['remove_bg', 'flush', 'rim'], 'opener' => ['remove_bg', 'flush', 'rim'],
        'box' => ['lid', 'cable_slot'], 'phone_stand' => ['cable', 'window', 'screws'], 'cable_holder' => ['screws'], 'modular' => ['tray'], 'vase' => ['drainage', 'saucer'], 'sign' => ['keyring', 'border', 'bevel', 'two_color'], 'logo' => ['invert', 'bevel'], 'stamp' => ['invert'], 'stencil' => ['invert'], 'papel' => ['invert', 'scallop', 'string_holes'], 'notes' => ['pen', 'invert'], 'hair_tie' => ['invert'], 'candle_stand' => ['invert'], 'insert' => ['notch'], 'lightbox' => ['invert'], 'qr' => ['stand', 'hole'], 'cutter' => ['stamp', 'invert'], 'holder' => ['mount'], 'cap' => ['grip']];

    /** kind → field, flag or choice → [choice key, values it belongs to]; the form hides it for the other choices. The key may also be a flag, its values are then on | off. */
    public const WHEN = [
        'beads' => ['relief' => ['style', ['raised']]],
        // a shape cut out or standing has no plate: what belongs to the plate shows only with it
        'logo' => ['bevel' => ['mode', ['cutout']], 'shape' => ['mode', ['relief', 'height']], 'plate' => ['mode', ['relief', 'height']], 'margin' => ['mode', ['relief', 'height']], 'base_h' => ['mode', ['standing']]],
        'cookie' => self::HANG_WHEN + self::SHAPE_WHEN, 'name_letter' => self::HANG_WHEN + self::SHAPE_WHEN,
        'medallion' => self::SHAPE_WHEN, 'bag_charm' => self::SHAPE_WHEN,
        // the clip and the tongue are always there: their place on the outline is not hidden behind a flag
        'straw' => ['bg_strength' => ['remove_bg', ['on']], 'relief' => ['flush', ['off']]], 'opener' => ['bg_strength' => ['remove_bg', ['on']], 'relief' => ['flush', ['off']]],
        // one colour and no picture in the floor: what belongs to the picture's colours never shows ("none" is no value of the choice)
        'photo_organizer' => ['cell' => ['inside', ['grid']], 'hole_d' => ['inside', ['holes']], 'frame' => ['inside', ['none']], 'relief' => ['inside', ['none']], 'colors_n' => ['inside', ['none']]] + self::SHAPE_WHEN,
        'badge' => self::SHAPE_WHEN + ['mag_d' => ['mount', ['glue']], 'mag_h' => ['mount', ['glue']]],
        // the step between colours means nothing in a floor ("none" is no value of the choice: the field never shows)
        'tray' => ['relief' => ['floor', ['none']], 'colors_n' => ['floor', ['engraved', 'colors']]] + self::SHAPE_WHEN,
        'topper' => self::SHAPE_WHEN, 'gingerbread' => self::SHAPE_WHEN, 'charm' => self::SHAPE_WHEN, 'keychain' => self::SHAPE_WHEN, 'earrings' => self::SHAPE_WHEN, 'ornament' => self::SHAPE_WHEN, 'coaster' => self::SHAPE_WHEN,
        'magnet' => self::SHAPE_WHEN + ['disc' => ['mount', ['glue', 'press', 'through']], 'mag_d' => ['mount', ['glue', 'press', 'through']], 'mag_h' => ['mount', ['glue', 'press']], 'mag_gap' => ['mount', ['glue']]],
        'phone_stand' => ['angle' => ['style', ['plate', 'wave', 'desk', 'wedge']], 'back' => ['style', ['plate', 'wave', 'desk']], 'depth' => ['style', ['wedge']], 'vent' => ['style', ['car']], 'thickness' => ['style', ['plate', 'wave', 'desk', 'wall', 'car']], 'cable' => ['style', ['wave', 'desk', 'wedge', 'wall', 'car']], 'window' => ['style', ['desk']], 'screws' => ['style', ['wall']]],
        'vase' => ['drainage' => ['purpose', ['pot']], 'saucer' => ['purpose', ['pot']], 'ribs' => ['style', ['twist', 'ribs']], 'flute' => ['style', ['twist', 'ribs']], 'twist' => ['style', ['twist']]],
        'sign' => ['shape' => ['style', ['emboss', 'engrave', 'outline']], 'radius' => ['shape', ['rounded']], 'border' => ['style', ['emboss', 'outline']], 'two_color' => ['style', ['emboss', 'outline', 'name']], 'ring_at' => ['keyring', ['on']],
            'bevel' => ['style', ['emboss', 'engrave', 'outline']], 'margin' => ['style', ['emboss', 'engrave', 'outline']],
            // a text that stands is letters and a foot: nothing is raised on it and nothing hangs
            'relief' => ['style', ['emboss', 'engrave', 'outline', 'name']], 'keyring' => ['style', ['emboss', 'engrave', 'outline', 'name']]],
        'holder' => ['obj_d' => ['style', ['cradle', 'pocket', 'hook']], 'height' => ['style', ['cradle', 'pocket', 'clip']], 'hook_h' => ['style', ['hook']], 'bend' => ['style', ['hook']],
            'edge' => ['style', ['hook', 'clip']], 'holes' => ['mount', ['on']], 'radius' => ['style', ['cradle', 'pocket']], 'clearance' => ['style', ['cradle', 'pocket', 'hook']]],
        'cap' => ['size_b' => ['shape', ['rect']], 'pitch' => ['style', ['thread']], 'grip' => ['style', ['push', 'thread']], 'head' => ['style', ['push']], 'edge' => ['head', ['flat']], 'seal' => ['style', ['push', 'thread']], 'mouth' => ['seal', ['lip']],
            'thread' => ['style', ['thread']], 'outer' => ['style', ['push', 'thread']]],
    ];

    /**
     * kind → choice → value → the fields it stands for. Choosing a value writes them into the form; a field edited by
     * hand puts the choice back to its first value. The server writes them too, so a saved design is consistent.
     * Threads: PET PCO 1881 (27.43 over the thread, pitch 2.7) and ISO metric coarse (major diameter, pitch).
     */
    public const FILLS = [
        // the discs sold everywhere: diameter × height
        'magnet' => ['disc' => ['d6x2' => ['mag_d' => 6, 'mag_h' => 2], 'd8x3' => ['mag_d' => 8, 'mag_h' => 3], 'd10x2' => ['mag_d' => 10, 'mag_h' => 2], 'd12x3' => ['mag_d' => 12, 'mag_h' => 3],
            'd15x3' => ['mag_d' => 15, 'mag_h' => 3], 'd20x3' => ['mag_d' => 20, 'mag_h' => 3]]],
        'cap' => ['thread' => [
            'pet28' => ['size_a' => 27.4, 'pitch' => 2.7],
            'm6' => ['size_a' => 6, 'pitch' => 1], 'm8' => ['size_a' => 8, 'pitch' => 1.25], 'm10' => ['size_a' => 10, 'pitch' => 1.5], 'm12' => ['size_a' => 12, 'pitch' => 1.75],
            'm14' => ['size_a' => 14, 'pitch' => 2], 'm16' => ['size_a' => 16, 'pitch' => 2], 'm20' => ['size_a' => 20, 'pitch' => 2.5], 'm24' => ['size_a' => 24, 'pitch' => 3], 'm30' => ['size_a' => 30, 'pitch' => 3.5],
        ]],
    ];

    /** flags that start switched on */
    public const FLAGS_ON = ['cable', 'window', 'drainage', 'saucer', 'border', 'stamp', 'mount', 'grip', 'remove_bg', 'eyelet', 'base', 'two_sides', 'scallop', 'string_holes', 'pen', 'notch'];

    /** things that hang only when asked to (a biscuit, a big letter): the eyelet's sizes show with the tick */
    private const HANG_WHEN = ['eye_pos' => ['hang', ['on']], 'eye_hole' => ['hang', ['on']], 'eye_wall' => ['hang', ['on']]];

    private const SHAPE_WHEN = ['bg_strength' => ['remove_bg', ['on']], 'eye_pos' => ['eyelet', ['on']], 'eye_hole' => ['eyelet', ['on']], 'eye_wall' => ['eyelet', ['on']], 'relief' => ['flush', ['off']]];

    public const PRESETS = [
        // three compositions to start from; colours are named, each becomes the farm's nearest spool
        'compose' => [
            'cloud' => ['base' => 'none', 'thickness' => 3, 'step' => 0.8, 'layers' => [
                ['kind' => 'shape', 'shape' => 'cloud', 'x' => 0, 'y' => 0, 'w' => 100, 'code' => 'white'],
                ['kind' => 'text', 'text' => 'Ela', 'typeface' => 'script', 'x' => 6, 'y' => -5, 'w' => 48, 'code' => 'blue'],
                ['kind' => 'art', 'art' => 'lib:hearts-stars/star', 'x' => -30, 'y' => 8, 'w' => 18, 'turn' => 15, 'code' => 'yellow'],
            ]],
            'topper' => ['base' => 'sticks', 'spike' => 60, 'thickness' => 3, 'step' => 0.8, 'layers' => [
                ['kind' => 'text', 'text' => '2', 'typeface' => 'archivo', 'x' => 0, 'y' => 0, 'w' => 62, 'code' => 'red'],
                ['kind' => 'text', 'text' => 'Olivia', 'typeface' => 'script', 'x' => 0, 'y' => -6, 'w' => 92, 'code' => 'white'],
            ]],
            'tag' => ['base' => 'eyelet', 'eye_hole' => 4, 'thickness' => 3, 'step' => 0.8, 'layers' => [
                ['kind' => 'shape', 'shape' => 'heart', 'x' => 0, 'y' => 0, 'w' => 60, 'code' => 'red'],
                ['kind' => 'text', 'text' => 'Ema', 'typeface' => 'lobster', 'x' => 0, 'y' => 4, 'w' => 34, 'code' => 'white'],
            ]],
        ],
        // the outline alone, pulled up: what "SVG to STL" means (its page /tools/svg-to-stl opens the logo tool with this)
        'logo' => ['extrude' => ['mode' => 'cutout', 'thickness' => 5, 'bevel' => false]],
        'vase' => [
            'spiral' => ['style' => 'twist', 'profile' => 'neck', 'height' => 180, 'top_d' => 62, 'bottom_d' => 54, 'ribs' => 20, 'flute' => 20, 'twist' => 200],
            'ribs' => ['style' => 'ribs', 'profile' => 'neck', 'height' => 170, 'top_d' => 70, 'bottom_d' => 60, 'ribs' => 18, 'flute' => 16],
            'smooth' => ['style' => 'smooth', 'profile' => 'neck', 'height' => 160, 'top_d' => 70, 'bottom_d' => 58],
            'pot' => ['purpose' => 'pot', 'style' => 'ribs', 'profile' => 'cone', 'height' => 120, 'top_d' => 130, 'bottom_d' => 100, 'ribs' => 16, 'flute' => 10],
        ],
        // gifts with a text: the same sign tool, four starting points (the landing page /gifts links to them)
        'sign' => [
            // a plate in a shape with a picture next to the name: what the card "nameplate" (/tools/nameplate) opens with
            'shaped' => ['style' => 'emboss', 'shape' => 'cloud', 'text_height' => 14, 'thickness' => 3, 'relief' => 1.2, 'margin' => 3, 'keyring' => false, 'border' => true, 'bevel' => false, 'two_color' => true, 'motif_at' => 'left'],
            // thick letters on a foot: what the card "text" (/tools/text) opens with
            'stand' => ['style' => 'stand', 'shape' => 'rect', 'typeface' => 'archivo', 'text_height' => 30, 'thickness' => 12, 'keyring' => false, 'border' => false, 'bevel' => false, 'two_color' => false],
            'name' => ['style' => 'name', 'typeface' => 'script', 'text_height' => 14, 'thickness' => 3, 'relief' => 1, 'keyring' => true, 'border' => false, 'bevel' => false, 'two_color' => false],
            'keyring' => ['style' => 'emboss', 'shape' => 'rounded', 'text_height' => 8, 'thickness' => 3, 'relief' => 1, 'margin' => 4, 'radius' => 6, 'keyring' => true, 'border' => true, 'bevel' => false, 'two_color' => true],
            'door' => ['style' => 'emboss', 'shape' => 'rounded', 'text_height' => 22, 'thickness' => 3, 'relief' => 1.4, 'margin' => 8, 'radius' => 8, 'keyring' => false, 'border' => true, 'bevel' => false, 'two_color' => true],
            'nametag' => ['style' => 'engrave', 'shape' => 'rect', 'text_height' => 10, 'thickness' => 2.4, 'relief' => 0.8, 'margin' => 4, 'keyring' => false, 'border' => false, 'bevel' => true, 'two_color' => false],
            'ornament' => ['style' => 'outline', 'shape' => 'oval', 'text_height' => 12, 'thickness' => 3, 'relief' => 1.2, 'margin' => 6, 'keyring' => true, 'border' => true, 'bevel' => false, 'two_color' => true],
        ],
        'cap' => [
            // PCO 1881: 27.4 over the thread, 21.7 inside the mouth; the lip seals, the thread only holds
            'pet' => ['style' => 'thread', 'thread' => 'pet28', 'shape' => 'round', 'size_a' => 27.4, 'height' => 12, 'pitch' => 2.7, 'wall' => 2, 'top' => 2, 'clearance' => 0.3, 'seal' => 'lip', 'mouth' => 21.7, 'outer' => 0],
            'pipe' => ['style' => 'plug', 'shape' => 'round', 'size_a' => 40, 'height' => 15, 'wall' => 2, 'top' => 2, 'clearance' => 0.2],
            'profile' => ['style' => 'plug', 'shape' => 'rect', 'size_a' => 36, 'size_b' => 16, 'height' => 15, 'wall' => 2, 'top' => 2, 'clearance' => 0.2],
            'jar' => ['style' => 'push', 'shape' => 'round', 'size_a' => 70, 'height' => 14, 'wall' => 2, 'top' => 2, 'clearance' => 0.3],
        ],
        'holder' => [
            'remote' => ['style' => 'cradle', 'obj_w' => 50, 'obj_d' => 22, 'height' => 70],
            'bottle' => ['style' => 'pocket', 'obj_w' => 75, 'obj_d' => 75, 'height' => 90, 'radius' => 12],
            'headphones' => ['style' => 'hook', 'obj_w' => 35, 'obj_d' => 45, 'hook_h' => 25, 'bend' => 15],
            'broom' => ['style' => 'clip', 'obj_w' => 24, 'height' => 25],
        ],
        'organizer' => [
            'drawer' => ['width' => 300, 'depth' => 200, 'height' => 45, 'rows' => 2, 'cols' => 4, 'radius' => 4, 'wall' => 1.6, 'floor' => 1.2],
            'office' => ['width' => 200, 'depth' => 100, 'height' => 60, 'rows' => 1, 'cols' => 3, 'wall' => 1.6, 'floor' => 1.2],
            'parts' => ['width' => 160, 'depth' => 120, 'height' => 25, 'rows' => 4, 'cols' => 5, 'wall' => 1.2, 'floor' => 1.0],
        ],
    ];

    public const MAX_HOLES = 8;

    public const MAX_BINS = 24;

    public const COLORS = ['white', 'black', 'grey', 'brown', 'red', 'blue', 'green', 'yellow', 'orange'];

    /**
     * The built-in colours as the preview paints them (FILAMENT in resources/js/calc/viewer.ts). A colour field carries
     * one of these names (designs from before the farm's catalogue, a site without a farm) or the `code` of a spool
     * in farm_colors: App\Domain\Farm\Palette knows both and is the one place that says what a colour looks like.
     */
    public const COLOR_HEX = Palette::BUILT_IN;

    /** A choice that is a filament colour: any colour of the palette is allowed, its hex is stored next to it. */
    public static function isColor(string $choice): bool
    {
        return str_ends_with($choice, '_color');
    }

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /**
     * Parts printed separately (the box and its lid, the four parts of an illuminated sign…); [] when the product is one body.
     *
     * @return list<string>
     */
    public static function partsOf(string $kind, array $p): array
    {
        return match ($kind) {
            'box' => ! empty($p['lid']) ? ['body', 'lid'] : [],
            'vase' => ($p['purpose'] ?? '') === 'pot' && ! empty($p['saucer']) ? ['body', 'saucer'] : [],
            'stamp' => ($p['handle'] ?? '') === 'knob' ? ['body', 'handle'] : [],
            'logo' => ($p['mode'] ?? '') === 'standing' ? ['body', 'stand'] : [],
            'notes', 'hair_tie', 'candle_stand' => ['body', 'stand'],
            'compose' => array_values(array_filter((array) ($p['parts'] ?? []), fn ($part) => is_string($part) && preg_match('/^layer_\d{1,2}$/', $part))),
            'sign' => ! empty($p['two_color']) && ! in_array($p['style'] ?? 'emboss', ['engrave', 'stand'], true) ? ['plate', 'text'] : [],
            'qr' => ! empty($p['stand']) ? ['body', 'stand'] : [],
            'lightbox' => ['body', 'face', 'diffuser', 'back'],
            'beads' => ($p['style'] ?? 'raised') === 'raised' ? ['body', 'text'] : [],                  // the beads and the letters raised on them: two colours
            'cutter' => array_values(array_intersect((array) ($p['parts'] ?? []), ['body', 'stamp'])),   // the stamp exists only when the drawing had inner lines: the tool says so
            'modular' => array_merge(! empty($p['tray']) ? ['tray'] : [], array_values(array_unique(array_map(fn ($b) => 'bin_'.$b['w'].'x'.$b['h'], (array) ($p['bins'] ?? []))))),
            // a picture in colours: the plate, every colour and the rim, as the tool listed them
            'charm', 'keychain', 'earrings', 'ornament', 'magnet', 'coaster', 'gingerbread', 'name_letter', 'cookie', 'topper', 'tray', 'badge', 'medallion', 'photo_organizer', 'bag_charm', 'straw', 'opener' => array_values(array_filter((array) ($p['parts'] ?? []), fn ($part) => is_string($part) && preg_match('/^(body|rim|color_[1-8]|icing_[1-6])$/', $part))),
            default => [],
        };
    }

    /** Laravel rules for one kind (used by the API; the same numbers are printed into the form as min/max). */
    public static function rules(string $kind): array
    {
        $rules = [];
        foreach (self::FIELDS[$kind] as $key => [$min, $max, , $step]) {
            $rules['params.'.$key] = ['nullable', $step === 1 ? 'integer' : 'numeric', 'min:'.$min, 'max:'.$max];
        }
        foreach (self::FLAGS[$kind] ?? [] as $flag) {
            $rules['params.'.$flag] = ['nullable', 'boolean'];
        }
        $palette = app(Palette::class);
        foreach (self::choicesOf($kind) as $key => $options) {
            $rules['params.'.$key] = ['nullable', Rule::in(self::isColor($key) ? $palette->codes() : $options)];
        }
        foreach (self::TEXTS[$kind] ?? [] as $key => [$max, $required]) {
            $rules['params.'.$key] = [$required ? 'required' : 'nullable', 'string', 'max:'.$max];
        }
        // which spool each separately printed part is meant for (the colours section of the tool page)
        $rules['params.part_colors'] = ['nullable', 'array', 'max:16'];
        $rules['params.part_colors.*'] = ['nullable', Rule::in($palette->codes())];
        if (in_array($kind, self::ARTWORK, true)) {
            $rules['params.artwork'] = ['nullable', 'string', 'regex:'.Artwork::REF];
        }
        if (isset(self::FAMILY[$kind])) {
            // colours of the picture the visitor joined into one, and their order from the bottom up (numbers of the list shown)
            $rules += [
                'params.merge' => ['nullable', 'array', 'max:8'], 'params.merge.*' => ['array', 'size:2'], 'params.merge.*.*' => ['integer', 'min:1', 'max:8'],
                'params.order' => ['nullable', 'array', 'max:8'], 'params.order.*' => ['integer', 'min:1', 'max:8'],
            ];
        }
        if ($kind === 'cookie') {
            // icing drawn by hand: every stroke a filament, a width, a nib and its points in shares of the picture's width
            $rules += [
                'params.strokes' => ['nullable', 'array', 'max:'.self::MAX_STROKES],
                'params.strokes.*.c' => ['required', Rule::in($palette->codes())],
                'params.strokes.*.w' => ['required', 'numeric', 'min:1.5', 'max:4'],
                'params.strokes.*.t' => ['required', 'in:round,flat,dots,candy,sprinkles'],
                'params.strokes.*.p' => ['required', 'array', 'min:1', 'max:'.self::MAX_STROKE_POINTS],
                'params.strokes.*.p.*' => ['array', 'size:2'],
                'params.strokes.*.p.*.*' => ['numeric', 'min:-1', 'max:4'],
            ];
        }
        if ($kind === 'modular') {
            $rules += [
                'params.bins' => ['required', 'array', 'min:1', 'max:'.self::MAX_BINS],
                'params.bins.*.x' => ['required', 'integer', 'min:0', 'max:11'],
                'params.bins.*.y' => ['required', 'integer', 'min:0', 'max:11'],
                'params.bins.*.w' => ['required', 'integer', 'min:1', 'max:12'],
                'params.bins.*.h' => ['required', 'integer', 'min:1', 'max:12'],
                'params.bins.*.color' => ['nullable', Rule::in($palette->codes())],
            ];
        }
        if ($kind === 'compose') {
            // the layers from the bottom up: a text, a picture of the library or a plain shape, where it lies, how wide, turned how, in which filament
            $rules += [
                'params.layers' => ['required', 'array', 'min:1', 'max:'.self::MAX_LAYERS],
                'params.layers.*.kind' => ['required', 'in:text,art,shape'],
                'params.layers.*.text' => ['nullable', 'string', 'max:40'],
                'params.layers.*.typeface' => ['nullable', Rule::in(array_keys(self::FONTS))],
                'params.layers.*.art' => ['nullable', 'string', 'regex:/^lib:[a-z-]{2,30}\/[a-z0-9-]{1,60}$/'],
                'params.layers.*.shape' => ['nullable', Rule::in(self::LAYER_SHAPES)],
                'params.layers.*.x' => ['nullable', 'numeric', 'min:-250', 'max:250'],
                'params.layers.*.y' => ['nullable', 'numeric', 'min:-250', 'max:250'],
                'params.layers.*.w' => ['nullable', 'numeric', 'min:5', 'max:250'],
                'params.layers.*.turn' => ['nullable', 'numeric', 'min:-360', 'max:360'],
                'params.layers.*.code' => ['nullable', Rule::in($palette->codes())],
                'params.layers.*.hidden' => ['nullable', 'boolean'],
            ];
        }
        if ($kind === 'box') {
            $rules += [
                'params.holes' => ['nullable', 'array', 'max:'.self::MAX_HOLES],
                'params.holes.*.wall' => ['required', 'in:front,back,left,right'],
                'params.holes.*.shape' => ['required', 'in:circle,rect'],
                'params.holes.*.w' => ['required', 'numeric', 'min:2', 'max:200'],
                'params.holes.*.h' => ['nullable', 'numeric', 'min:2', 'max:200'],
                'params.holes.*.x' => ['required', 'numeric', 'min:0', 'max:300'],
                'params.holes.*.z' => ['required', 'numeric', 'min:0', 'max:200'],
            ];
        }

        return $rules;
    }

    /** Known keys only, numbers as numbers, defaults filled in: what is stored and what the tool receives. */
    public static function clean(string $kind, array $p): array
    {
        $out = [];
        foreach (self::FIELDS[$kind] as $key => [, , $default, $step]) {
            $v = $p[$key] ?? $default;
            $out[$key] = $step === 1 ? (int) $v : round((float) $v, 2);
        }
        foreach (self::FLAGS[$kind] ?? [] as $flag) {
            $out[$flag] = filter_var($p[$flag] ?? in_array($flag, self::FLAGS_ON, true), FILTER_VALIDATE_BOOLEAN);
        }
        $palette = app(Palette::class);
        foreach (self::choicesOf($kind) as $key => $options) {
            if (self::isColor($key)) {
                // the code of the spool and what it looks like: the preview stays right when the spool leaves the stock
                $out[$key] = is_string($p[$key] ?? null) && $palette->has($p[$key]) ? $p[$key] : $options[0];
                $out[$key.'_hex'] = $palette->hex($out[$key]) ?? self::COLOR_HEX[$options[0]];

                continue;
            }
            $out[$key] = in_array($p[$key] ?? null, $options, true) ? $p[$key] : $options[0];
            // a standard picked by name (an M10 thread) brings its own numbers, whatever the form sent
            foreach (self::FILLS[$kind][$key][$out[$key]] ?? [] as $field => $value) {
                $out[$field] = $value;
            }
        }
        foreach (self::TEXTS[$kind] ?? [] as $key => [$max, , $default]) {
            // an emptied field stays empty (the framework turns '' into null); the default is only for a field that was never sent
            $out[$key] = mb_substr(trim((string) (array_key_exists($key, $p) ? ($p[$key] ?? '') : $default)), 0, $max);
        }
        if (in_array($kind, self::ARTWORK, true) && ! empty($p['artwork'])) {
            $out['artwork'] = (string) $p['artwork'];
        }
        // part → the code of its spool and what it looks like (a stored design comes back with both)
        foreach (array_slice((array) ($p['part_colors'] ?? []), 0, 16, true) as $part => $color) {
            $code = is_array($color) ? ($color['code'] ?? null) : $color;
            if (is_string($part) && preg_match('/^[a-z0-9_]{1,24}$/', $part) && is_string($code) && $palette->has($code)) {
                $out['part_colors'][$part] = ['code' => $code, 'hex' => $palette->hex($code)];
            }
        }
        if (isset(self::FAMILY[$kind])) {
            $out['merge'] = array_values(array_map(fn ($pair) => [(int) $pair[0], (int) $pair[1]], array_filter(array_slice((array) ($p['merge'] ?? []), 0, 8), fn ($pair) => is_array($pair) && count($pair) === 2)));
            $out['order'] = array_values(array_unique(array_map('intval', array_slice((array) ($p['order'] ?? []), 0, 8))));
        }
        if ($kind === 'compose') {
            $spare = $palette->codes()[0] ?? '';
            $named = $palette->legacy();             // "white", "blue"… as the spools the farm has for them
            $out['layers'] = array_values(array_map(fn ($l) => [
                'kind' => in_array($l['kind'] ?? '', ['text', 'art', 'shape'], true) ? $l['kind'] : 'shape',
                'text' => mb_substr(trim((string) ($l['text'] ?? '')), 0, 40),
                'typeface' => isset(self::FONTS[$l['typeface'] ?? '']) ? $l['typeface'] : 'sans',
                'art' => (string) ($l['art'] ?? ''),
                'shape' => in_array($l['shape'] ?? '', self::LAYER_SHAPES, true) ? $l['shape'] : 'rounded',
                'x' => round((float) ($l['x'] ?? 0), 2), 'y' => round((float) ($l['y'] ?? 0), 2), 'w' => round((float) ($l['w'] ?? 50), 2), 'turn' => round((float) ($l['turn'] ?? 0), 1),
                'code' => is_string($l['code'] ?? null) && $palette->has($l['code']) ? ($named[$l['code']] ?? $l['code']) : $spare,
                'hidden' => filter_var($l['hidden'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ], array_filter(array_slice((array) ($p['layers'] ?? []), 0, self::MAX_LAYERS), 'is_array')));
        }
        if ($kind === 'cookie') {
            $out['strokes'] = array_values(array_filter(array_map(function ($s) use ($palette) {
                $points = array_values(array_filter(array_slice((array) ($s['p'] ?? []), 0, self::MAX_STROKE_POINTS), fn ($pt) => is_array($pt) && count($pt) === 2));
                if (! $points || ! is_string($s['c'] ?? null) || ! $palette->has($s['c'])) {
                    return null;
                }

                return ['c' => $s['c'], 'h' => $palette->hex($s['c']), 'w' => round(min(4, max(1.5, (float) ($s['w'] ?? 2.5))), 1),
                    't' => in_array($s['t'] ?? '', ['round', 'flat', 'dots', 'candy', 'sprinkles'], true) ? $s['t'] : 'round',
                    'p' => array_map(fn ($pt) => [round((float) array_values($pt)[0], 4), round((float) array_values($pt)[1], 4)], $points)];
            }, array_slice((array) ($p['strokes'] ?? []), 0, self::MAX_STROKES))));
        }
        if ($kind === 'modular') {
            $out['bins'] = array_values(array_map(function ($b) use ($palette) {
                $color = is_string($b['color'] ?? null) && $palette->has($b['color']) ? $b['color'] : 'white';

                return ['x' => (int) $b['x'], 'y' => (int) $b['y'], 'w' => (int) $b['w'], 'h' => (int) $b['h'], 'color' => $color]
                    + (isset(self::COLOR_HEX[$color]) ? [] : ['hex' => $palette->hex($color)]);   // a built-in name needs no hex: old designs stay as they were stored
            }, array_slice((array) ($p['bins'] ?? []), 0, self::MAX_BINS)));
        }
        if ($kind === 'box') {
            $out['holes'] = array_values(array_map(fn ($h) => [
                'wall' => (string) $h['wall'], 'shape' => (string) $h['shape'], 'w' => round((float) $h['w'], 1),
                'h' => round((float) ($h['h'] ?? $h['w']), 1), 'x' => round((float) $h['x'], 1), 'z' => round((float) $h['z'], 1),
            ], array_slice((array) ($p['holes'] ?? []), 0, self::MAX_HOLES)));
        }

        return $out;
    }

    /**
     * @param  bool  $pieces  group the triangles by the piece they belong to and say so in meta.parts (the tool page's preview)
     * @return array{path: string, meta: array<string, mixed>} temporary STL (caller deletes) + size, volume, notes
     */
    public function build(string $kind, array $params, string $part = 'all', string $view = 'print', bool $pieces = false): array
    {
        if (! isset(self::FIELDS[$kind])) {
            throw new EngineException('Unknown product.');
        }
        $dir = storage_path('app/tmp/param');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.Str::uuid().'.stl';
        $json = (string) json_encode($this->forTool($kind, self::clean($kind, $params)), JSON_UNESCAPED_UNICODE);
        // a drawing of many strokes is longer than a command line may be: the tool then reads it from a file
        $long = strlen($json) > 12000 ? $dir.'/'.Str::uuid().'.json' : null;
        $long && File::put($long, $json);
        try {
            $r = $this->python->runScript('param_tool.py', [$kind, $path, $long ? '@'.$long : $json, $part, $view, ...($pieces ? ['parts'] : [])], 60);
        } finally {
            $long && @unlink($long);
        }
        if (empty($r['ok']) || ! is_file($path)) {
            @unlink($path);
            $code = (string) ($r['code'] ?? 'failed');
            // geometry the numbers cannot make (cells too small, openings that collide…): a form error, not a crash
            throw ValidationException::withMessages(['params' => [self::explain($code, (string) ($r['error'] ?? ''))]])->status(422);
        }

        return ['path' => $path, 'meta' => ['part' => $r['part'] ?? 'all', 'bbox' => $r['bbox'], 'volume_mm3' => $r['volume_mm3'], 'area_mm2' => $r['area_mm2'], 'triangles' => $r['triangles'], 'notes' => $r['notes'] ?? []] + ($pieces ? ['parts' => $r['parts'] ?? []] : [])];
    }

    /** Adds what only the server knows: the font file and where the uploaded artwork lives. */
    private function forTool(string $kind, array $clean): array
    {
        if (isset(self::TEXTS[$kind]) || in_array($kind, self::ARTWORK, true)) {
            $clean['font'] = self::fontPath($clean['typeface'] ?? null);
            $clean['lines'] = array_values(array_filter([$clean['line1'] ?? '', $clean['line2'] ?? '', $clean['line3'] ?? ''], fn ($l) => $l !== ''));
            if (isset($clean['letter_face']) || $kind === 'topper') {
                // the big letter has a typeface of its own: always a bold one, it carries the name
                $clean['letter_font'] = self::fontPath(in_array($clean['letter_face'] ?? '', ['sans', 'serif', 'mono'], true) ? $clean['letter_face'] : 'sans');
            }
        }
        if (isset(self::FAMILY[$kind])) {
            $clean['palette'] = self::spools();
        }
        if ($kind === 'compose') {
            $palette = app(Palette::class);
            foreach ($clean['layers'] ?? [] as $i => $layer) {
                // every layer brings its own font, picture or shape and the look of its filament
                $clean['layers'][$i]['font'] = self::fontPath($layer['typeface']);
                $clean['layers'][$i]['hex'] = $palette->hex($layer['code']) ?? '#888888';
                if ($layer['kind'] === 'art') {
                    $clean['layers'][$i]['art_path'] = self::artworkPath($layer['art']);
                    if (! $clean['layers'][$i]['art_path']) {
                        throw ValidationException::withMessages(['params' => [__('param.error.artwork_gone')]])->status(422);
                    }
                }
                if ($layer['kind'] === 'shape' && ! in_array($layer['shape'], ['rounded', 'rect', 'circle'], true)) {
                    $clean['layers'][$i]['shape_path'] = base_path('engines/shapes/'.$layer['shape'].'.svg');
                }
            }
        }
        if (! empty($clean['artwork'])) {
            $clean['artwork_path'] = self::artworkPath($clean['artwork']);
            if (! $clean['artwork_path']) {
                throw ValidationException::withMessages(['params' => [__('param.error.artwork_gone')]])->status(422);
            }
        } elseif (in_array($kind, self::ARTWORK, true) && empty($clean['lines'])) {
            throw ValidationException::withMessages(['params' => [__('param.error.no_text')]])->status(422);
        }

        return $clean;
    }

    /**
     * The filaments a picture's colours are matched to by themselves: plain colours in stock, of the plastic most of them
     * are made of (one print is one kind of plastic). The visitor can still give any spool of the catalogue to a colour.
     *
     * @return list<array{0: string, 1: string}> [code, hex]
     */
    public static function spools(): array
    {
        $rows = array_values(array_filter(app(Palette::class)->all(), fn ($c) => $c['in_stock'] && $c['hue'] !== 'special'));
        $materials = array_count_values(array_column($rows, 'material'));
        arsort($materials);
        $main = array_key_first($materials);

        return array_values(array_map(fn ($c) => [$c['code'], $c['hex']], array_filter($rows, fn ($c) => $c['material'] === $main)));
    }

    /** An upload, a silhouette of the library or the copy kept with a created model: see App\Domain\Tools\Artwork. */
    public static function artworkPath(string $ref): ?string
    {
        return Artwork::path($ref);
    }

    /** Uploaded SVG or picture → a reference the form sends along with the numbers; with an owner it shows among "my pictures". */
    public static function storeArtwork(UploadedFile $file, ?string $owner = null): string
    {
        return Artwork::store($file, $owner);
    }

    /**
     * Every separately printed part of a design as its own STL in one ZIP (and the whole set as it is laid out).
     *
     * @return string path of a temporary ZIP (caller deletes)
     */
    public function zip(string $kind, array $params): string
    {
        $clean = self::clean($kind, $params);
        $whole = $this->build($kind, $clean);
        if (isset($whole['meta']['notes']['parts'])) {
            $clean['parts'] = array_values((array) $whole['meta']['notes']['parts']);
        }
        $name = str_replace('_', '-', $kind);
        $path = storage_path('app/tmp/param/'.Str::uuid().'.zip');
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($whole['path']);
            throw new EngineException('The archive could not be written.');
        }
        $temp = [$whole['path']];
        $zip->addFile($whole['path'], $name.'.stl');
        foreach (array_diff(self::partsOf($kind, $clean), ['all']) as $part) {
            $one = $this->build($kind, $clean, $part);
            $temp[] = $one['path'];
            if ($one['meta']['part'] === $part) {
                $zip->addFile($one['path'], $name.'-'.$part.'.stl');
            }
        }
        $zip->close();
        array_map(fn ($t) => @unlink($t), $temp);

        return $path;
    }

    public static function explain(string $code, string $raw = ''): string
    {
        $key = 'param.error.'.$code;
        $n = preg_match('/:\s*(.+)$/', $raw, $m) ? $m[1] : '';
        $text = __($key, ['n' => $n]);

        return $text === $key ? __('param.error.failed') : $text;
    }

    public function create(string $kind, array $params, ?AnonymousSession $session, ?User $user): ModelFile
    {
        $clean = self::clean($kind, $params);
        $built = $this->build($kind, $clean);
        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));
        File::move($built['path'], $abs);
        if (! empty($clean['artwork']) && ($src = self::artworkPath($clean['artwork']))) {
            File::copy($src, dirname($abs).'/artwork.'.pathinfo($src, PATHINFO_EXTENSION));
            $clean['artwork'] = 'file:'.$uuid;
        }

        if (isset($built['meta']['notes']['color_change_mm'])) {
            // a plate with a raised text or motif: from this height up the print farm can switch to a second colour
            $clean['color_change_mm'] = (float) $built['meta']['notes']['color_change_mm'];
        }
        if (isset($built['meta']['notes']['parts'])) {
            $clean['parts'] = array_values((array) $built['meta']['notes']['parts']);   // which separately printed parts this design really has
        }
        if (isset(self::FAMILY[$kind])) {
            $notes = $built['meta']['notes'];
            // the filaments the tool chose are kept with the design: opened again next month it looks the same, whatever the catalogue holds by then
            $chosen = ['body' => $notes['body_color'] ?? null, 'rim' => $notes['rim_color'] ?? null] + array_column((array) ($notes['colors'] ?? []), null, 'part');
            foreach ($chosen as $part => $color) {
                if (is_array($color) && ! empty($color['code']) && ! isset($clean['part_colors'][$part]) && in_array($part, $clean['parts'] ?? [], true)) {
                    $clean['part_colors'][$part] = ['code' => (string) $color['code'], 'hex' => (string) $color['hex']];
                }
            }
            // where the print changes filament by height (more than one place: color_change_mm above knows only one), and
            // whether colours share a layer, which only a printer that changes filament by itself can print
            $clean['color_changes'] = array_values((array) ($notes['color_changes'] ?? []));
            $clean['multi_material'] = ! empty($notes['multi_material']);
        }
        if ($kind === 'compose') {
            // layers lie one on another like the colours of a picture: the same notes for the farm and the slicer projects
            foreach ((array) ($built['meta']['notes']['layers'] ?? []) as $layer) {
                $clean['part_colors'][$layer['part']] = ['code' => (string) $layer['code'], 'hex' => (string) $layer['hex']];
            }
            $clean['color_changes'] = array_values((array) ($built['meta']['notes']['color_changes'] ?? []));
            $clean['multi_material'] = false;
        }
        // what has to fit a printer is each part alone, not the plate they are laid out on: the check reads these sizes
        $parts = array_diff(self::partsOf($kind, $clean), ['all']);
        if ($parts && ! in_array($kind, ['modular', 'compose'], true) && ! isset(self::FAMILY[$kind])) {       // the colours of a picture all lie on the one plate
            foreach ($parts as $part) {
                $one = $this->build($kind, $clean, $part);
                @unlink($one['path']);
                $clean['parts_bbox'][$part] = [(float) $one['meta']['bbox']['x'], (float) $one['meta']['bbox']['y'], (float) $one['meta']['bbox']['z']];
            }
        }

        $o = $built['meta']['notes']['outer'] ?? [$built['meta']['bbox']['x'], $built['meta']['bbox']['y'], $built['meta']['bbox']['z']];
        $name = str_replace('_', '-', $kind).'-'.implode('x', array_map(fn ($v) => (string) round((float) $v), $o));
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user?->id, 'anonymous_session_id' => $session?->id,
            'original_name' => $name.'.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => $kind, 'tool_params' => $clean, 'status' => ModelFile::STATUS_UPLOADED,
        ]);
        ProcessModelFile::dispatch($file->id);

        return $file->refresh();
    }
}
