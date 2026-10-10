<?php

namespace App\Http\Controllers;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Tools\MapBuilder;
use App\Domain\Tools\MapData;
use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Api\ConfigController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The 3D map of a city or a landscape: a place, its area from above, the settings, the colours, the model made in the queue. */
class MapToolController extends Controller
{
    public function show(Request $request, MapBuilder $maps, MaterialCatalog $materials, ConverterChain $converters): View
    {
        $from = $request->query('from');

        return view('tools.map', [
            'available' => $maps->available(),
            'fields' => MapBuilder::FIELDS, 'choices' => MapBuilder::CHOICES, 'sides' => MapBuilder::SIDES, 'flags' => MapBuilder::FLAGS, 'flagsOn' => MapBuilder::FLAGS_ON,
            'parts' => MapBuilder::PARTS, 'colors' => MapBuilder::COLORS, 'daily' => MapBuilder::DAILY,
            'attribution' => MapData::ATTRIBUTION,
            'from' => is_string($from) && preg_match('/^[0-9a-f-]{36}$/', $from) ? $from : null,
            'config' => ConfigController::payload($materials, $converters, true),
        ]);
    }
}
