<?php

namespace App\Http\Controllers;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Farm\Palette;
use App\Domain\Tools\ModelPainter;
use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Api\ConfigController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The tool "Vybarvit model": a model of the visitor's (uploaded here, or any ready file with ?from=uuid) painted in filament colours. */
class PaintToolController extends Controller
{
    public function show(Request $request, ModelPainter $painter, Palette $palette, MaterialCatalog $materials, ConverterChain $converters): View
    {
        $from = $request->query('from');

        return view('tools.paint', [
            'available' => $painter->available(),
            'limits' => ModelPainter::limits(),
            'tiles' => ModelPainter::tiles($palette),
            'fromFarm' => $palette->fromFarm(),
            'from' => is_string($from) && preg_match('/^[0-9a-f-]{36}$/', $from) ? $from : null,
            'config' => ConfigController::payload($materials, $converters, true),
        ]);
    }
}
