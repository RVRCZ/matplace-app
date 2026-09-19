<?php

namespace App\Http\Controllers;

use App\Domain\Calculation\MaterialCatalog;
use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Api\CalculationController;
use App\Http\Controllers\Api\ConfigController;
use App\Models\Calculation;
use Illuminate\Contracts\View\View;

class CalculatorController extends Controller
{
    public function index(MaterialCatalog $materials, ConverterChain $converters): View
    {
        return view('calculator.index', [
            'config' => ConfigController::payload($materials, $converters),
            'initial' => null,
        ]);
    }

    /** /k/{token} — shared result, no account needed. */
    public function share(Calculation $calculation, MaterialCatalog $materials, ConverterChain $converters): View
    {
        return view('calculator.index', [
            'config' => ConfigController::payload($materials, $converters),
            'initial' => CalculationController::describe($calculation->load('modelFile')),
        ]);
    }
}
