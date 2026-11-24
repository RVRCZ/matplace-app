<?php

namespace App\Http\Controllers;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Farm\Palette;
use App\Domain\Tools\ModelPainter;
use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Api\ConfigController;
use App\Models\ModelFile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The tool "Vybarvit model": a model of the visitor's (uploaded here, or any ready file with ?from=uuid) painted in filament colours. */
class PaintToolController extends Controller
{
    public function show(Request $request, ModelPainter $painter, Palette $palette, MaterialCatalog $materials, ConverterChain $converters): View
    {
        $from = $request->query('from');
        $from = is_string($from) && preg_match('/^[0-9a-f-]{36}$/', $from) ? $from : null;
        // a generated bust or pet painted by its parts already (ModelPainter::auto): the painting starts from those colours
        $twin = $from ? ModelPainter::twinOf(ModelFile::where('uuid', $from)->first() ?? new ModelFile) : null;
        if ($twin && $twin->isReady()) {
            $from = $twin->uuid;
        }

        return view('tools.paint', [
            'available' => $painter->available(),
            'limits' => ModelPainter::limits(),
            'tiles' => ModelPainter::tiles($palette),
            'fromFarm' => $palette->fromFarm(),
            'from' => $from,
            'config' => ConfigController::payload($materials, $converters, true),
        ]);
    }
}
