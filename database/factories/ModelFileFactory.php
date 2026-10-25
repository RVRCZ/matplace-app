<?php

namespace Database\Factories;

use App\Models\ModelFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A processed upload as a row alone (no file on any disk): enough for an order, an event or a video to hang on.
 *
 * @extends Factory<ModelFile>
 */
class ModelFileFactory extends Factory
{
    protected $model = ModelFile::class;

    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'original_name' => 'cube.stl',
            'ext' => 'stl',
            'mime' => 'model/stl',
            'size_bytes' => 684,
            'sha256' => hash('sha256', $uuid),
            'storage_path' => 'files/'.$uuid.'/original.stl',
            'status' => ModelFile::STATUS_READY,
            'triangles' => 12,
            'volume_mm3' => 8000,
            'area_mm2' => 2400,
            'bbox' => ['x' => 20, 'y' => 20, 'z' => 20],
        ];
    }
}
