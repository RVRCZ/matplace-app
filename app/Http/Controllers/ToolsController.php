<?php

namespace App\Http\Controllers;

use App\Domain\Generation\GenerationService;
use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Tools\ParametricGenerator;
use App\Domain\Tools\ReliefGenerator;
use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Api\ConfigController;
use App\Domain\Tools\MoldGenerator;
use App\Domain\Tools\SignGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** "Tools" menu: everything that does not belong on the one main screen. */
class ToolsController extends Controller
{
    public function index(GenerationService $generation): View
    {
        return view('tools.index', ['generator' => $generation->enabled()]);
    }

    public function sign(SignGenerator $signs): View
    {
        return view('tools.sign', ['available' => $signs->available(), 'fonts' => array_keys($signs->fonts())]);
    }

    public function relief(ReliefGenerator $reliefs): View
    {
        return view('tools.relief', ['available' => $reliefs->available()]);
    }

    /** Organizer, box, phone stand, cable holder: one page, the fields come from the generator's own limits. */
    public function param(string $kind, ParametricGenerator $tools, MaterialCatalog $materials, ConverterChain $converters): View
    {
        abort_unless(isset(ParametricGenerator::FIELDS[$kind]), 404);

        return view('tools.param', [
            'kind' => $kind,
            'available' => $tools->available(),
            'fields' => ParametricGenerator::FIELDS[$kind],
            'flags' => ParametricGenerator::FLAGS[$kind] ?? [],
            'when' => \App\Domain\Tools\ParametricGenerator::WHEN[$kind] ?? [], 'flagsOn' => ParametricGenerator::FLAGS_ON,
            'choices' => ParametricGenerator::CHOICES[$kind] ?? [],
            'texts' => ParametricGenerator::TEXTS[$kind] ?? [],
            'artwork' => in_array($kind, ParametricGenerator::ARTWORK, true),
            'main' => ParametricGenerator::MAIN[$kind],
            'presets' => ParametricGenerator::PRESETS[$kind] ?? [],
            'config' => ConfigController::payload($materials, $converters),
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
            'walls' => MoldGenerator::WALLS, 'axes' => MoldGenerator::AXES, 'splits' => MoldGenerator::SPLITS,
        ]);
    }

    /** "Check my model": the upload, the viewer and a plain-language report; the price is one click further. */
    public function check(MaterialCatalog $materials, ConverterChain $converters): View
    {
        return view('tools.check', ['config' => ConfigController::payload($materials, $converters)]);
    }

    public function figure(GenerationService $generation): View
    {
        return view('tools.figure', [
            'generator' => $generation->enabled(),
            'guestLimit' => (int) config('ai.daily_limits.generate_guest'),
            'userLimit' => $generation->limitFor(auth()->user()) > (int) config('ai.daily_limits.generate_user') ? $generation->limitFor(auth()->user()) : (int) config('ai.daily_limits.generate_user'),
        ]);
    }
}
