<?php

namespace App\Http\Controllers;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Generation\GenerationService;
use App\Domain\Tools\ModelRepair;
use App\Domain\Tools\MoldGenerator;
use App\Domain\Tools\ParametricGenerator;
use App\Domain\Tools\ReliefGenerator;
use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Api\ConfigController;
use App\Models\ModelFile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** "Tools" menu: everything that does not belong on the one main screen. */
class ToolsController extends Controller
{
    public function index(GenerationService $generation): View
    {
        return view('tools.index', ['generator' => $generation->enabled()]);
    }

    public function relief(ReliefGenerator $reliefs, MaterialCatalog $materials, ConverterChain $converters): View
    {
        return view('tools.relief', ['available' => $reliefs->available(), 'config' => ConfigController::payload($materials, $converters)]);
    }

    /** Organizer, box, phone stand, cable holder: one page, the fields come from the generator's own limits. */
    public function param(Request $request, string $kind, ParametricGenerator $tools, MaterialCatalog $materials, ConverterChain $converters): View
    {
        // a tool of the catalogue that is another tool opened with a preset (SVG to STL = the logo tool's plain extrusion):
        // the route names the tool whose title, texts and card the page shows, and the preset it starts with
        $tool = (string) ($request->route('as') ?? $kind);
        $preset = $request->route('preset');
        // an address that became the composer keeps the quick form it used to be: for who asks for it, and for a design made with it
        [$form, $formPreset] = explode(':', (string) $request->route('form')) + [1 => null];
        $from = $request->query('from');
        $quick = $form !== '' && ($request->boolean('form')
            || (is_string($from) && preg_match('/^[0-9a-f-]{36}$/', $from) && ModelFile::where('uuid', $from)->value('origin_ref') === $form));
        if ($quick) {
            [$kind, $preset] = [$form, $formPreset];
        }
        abort_unless(isset(ParametricGenerator::FIELDS[$kind]), 404);

        return view('tools.param', [
            'kind' => $kind,
            'tool' => $tool,
            'preset' => is_string($preset) && isset(ParametricGenerator::PRESETS[$kind][$preset]) ? $preset : null,
            'available' => $tools->available(),
            'fields' => ParametricGenerator::FIELDS[$kind],
            'flags' => ParametricGenerator::FLAGS[$kind] ?? [],
            'when' => ParametricGenerator::WHEN[$kind] ?? [], 'flagsOn' => ParametricGenerator::FLAGS_ON,
            'choices' => ParametricGenerator::choicesOf($kind),
            'texts' => ParametricGenerator::TEXTS[$kind] ?? [],
            'artwork' => in_array($kind, ParametricGenerator::ARTWORK, true),
            'main' => ParametricGenerator::MAIN[$kind],
            'presets' => ParametricGenerator::PRESETS[$kind] ?? [],
            'fills' => ParametricGenerator::FILLS[$kind] ?? [],
            'family' => ParametricGenerator::FAMILY[$kind] ?? null,
            'place' => ParametricGenerator::PLACE[ParametricGenerator::FAMILY[$kind] ?? $kind] ?? [],
            'sample' => $kind === 'compose' ? null : (ParametricGenerator::SAMPLE[$tool] ?? ParametricGenerator::SAMPLE[$kind] ?? null),
            'captioned' => in_array($kind, ParametricGenerator::CAPTIONED, true),
            'fonts' => ParametricGenerator::FONTS,
            'layerShapes' => ParametricGenerator::LAYER_SHAPES,
            'composeAs' => ParametricGenerator::COMPOSED[$tool] ?? null,
            'quickForm' => $form === '' ? null : $quick,       // null: the address has no quick form; true: this is it; false: this is the composer
            'config' => ConfigController::payload($materials, $converters, true),
        ]);
    }

    public function spare(MaterialCatalog $materials): View
    {
        return view('tools.spare', ['materials' => $materials->all()]);
    }

    /** "Casting mold": upload a model (or come from the calculator with ?from=uuid), pick the wall and the split, get both halves. */
    public function mold(Request $request, MoldGenerator $molds, MaterialCatalog $materials, ConverterChain $converters): View
    {
        $from = $request->query('from');

        return view('tools.mold', [
            'available' => $molds->available(),
            'config' => ConfigController::payload($materials, $converters),
            'from' => is_string($from) && preg_match('/^[0-9a-f-]{36}$/', $from) ? $from : null,
            'types' => MoldGenerator::TYPES, 'parts' => MoldGenerator::PARTS, 'walls' => MoldGenerator::WALLS, 'axes' => MoldGenerator::AXES, 'splits' => MoldGenerator::SPLITS,
        ]);
    }

    /** Gifts with a name: occasions and products, every one a link into the sign tool with a preset and a sample text. */
    public function gifts(): View
    {
        return view('tools.gifts');
    }

    /** "Repair my model": upload, automatic repair, what was wrong and what was done, download and price. */
    public function repair(ModelRepair $repairs, MaterialCatalog $materials, ConverterChain $converters): View
    {
        return view('tools.repair', ['available' => $repairs->available(), 'config' => ConfigController::payload($materials, $converters)]);
    }

    /** "Check my model": the upload, the viewer and a plain-language report; the price is one click further. */
    public function check(MaterialCatalog $materials, ConverterChain $converters): View
    {
        return view('tools.check', ['config' => ConfigController::payload($materials, $converters)]);
    }

    public function figure(GenerationService $generation, MaterialCatalog $materials, ConverterChain $converters): View
    {
        return view('tools.figure', [
            'config' => ConfigController::payload($materials, $converters),
            'generator' => $generation->enabled(),
            'guestLimit' => (int) config('ai.daily_limits.generate_guest'),
            // the page explains the limits of accounts; the admin's own (none) is not what it is about
            'userLimit' => max((int) config('ai.daily_limits.generate_user'), auth()->user()?->isAdmin() ? 0 : $generation->limitFor(auth()->user())),
        ]);
    }
}
