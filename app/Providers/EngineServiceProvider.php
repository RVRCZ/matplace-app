<?php

namespace App\Providers;

use App\Domain\Calculation\RoughEstimator;
use App\Engines\Contracts\MeshRepair;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\Contracts\Slicer;
use App\Engines\Converter\ConverterChain;
use App\Engines\Converter\FreeCadConverter;
use App\Engines\Converter\PythonMeshConverter;
use App\Engines\Converter\ThreeMfConverter;
use App\Engines\Generator\NullGenerator;
use App\Engines\Repair\PhpStlRepair;
use App\Engines\Repair\PythonTool;
use App\Engines\Repair\TrimeshRepair;
use App\Engines\Slicer\FakeSlicer;
use App\Engines\Slicer\OrcaSlicer;
use Illuminate\Support\ServiceProvider;

/** Binds engine contracts to implementations chosen in config/engines.php. */
class EngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PythonTool::class, fn () => new PythonTool(config('engines.python')));

        $this->app->singleton(Slicer::class, function ($app) {
            return match (config('engines.slicer')) {
                'fake' => new FakeSlicer($app->make(RoughEstimator::class)),
                default => new OrcaSlicer(config('engines.orca')),
            };
        });

        $this->app->singleton(MeshRepair::class, function ($app) {
            $python = $app->make(PythonTool::class);
            if (config('engines.repair') === 'trimesh' && $python->available()) {
                return new TrimeshRepair($python);
            }

            return new PhpStlRepair;
        });

        $this->app->singleton(ModelGenerator::class, fn () => new NullGenerator);

        $this->app->singleton(ConverterChain::class, function ($app) {
            $map = [
                'threemf' => fn () => new ThreeMfConverter,
                'freecad' => fn () => new FreeCadConverter(config('engines.freecad')),
                'trimesh' => fn () => new PythonMeshConverter($app->make(PythonTool::class)),
            ];
            $list = [];
            foreach (config('engines.converters', []) as $key) {
                if (isset($map[$key])) {
                    $list[] = $map[$key]();
                }
            }

            return new ConverterChain($list);
        });
    }
}
