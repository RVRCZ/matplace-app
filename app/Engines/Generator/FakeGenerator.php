<?php

namespace App\Engines\Generator;

use App\Engines\Contracts\ImageRestyler;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Mesh\StlFile;
use Illuminate\Support\Str;

/** Deterministic generator for tests and offline development: always "generates" a unit cube STL. */
final class FakeGenerator implements ImageRestyler, ModelGenerator
{
    /** The prompts the photos were redrawn with, in order: a test can tell which wording a request was cleaned by. */
    public static array $restyled = [];

    /** A test of the coloured result: a GLB file the generator hands over instead of the cube (copied to a temp file, the job moves it). */
    public static ?string $glb = null;

    /** A test of a cleaning that fails: the picture task then ends as the real one does when the image model refuses. */
    public static bool $imageFails = false;

    public function name(): string
    {
        return 'fake';
    }

    public function estimatedCostCents(): int
    {
        return 0;
    }

    public function fromText(string $prompt, GenerationOptions $options): GenerationHandle
    {
        return new GenerationHandle('fake', 'fake-'.Str::random(8));
    }

    public function fromImage(string $imagePath, ?string $hint, GenerationOptions $options): GenerationHandle
    {
        // "credits" as the real engine counts them, so a test can tell which quality was asked for
        return new GenerationHandle('fake', 'fake-'.Str::random(8), $options->geometryQuality ? ['credits' => $options->geometryQuality === 'detailed' ? 40 : 20] : []);
    }

    /** The photo "redrawn": a new little picture, so a test can tell the model was made of it and not of the photo. */
    public function restyle(string $imagePath, string $prompt): GenerationHandle
    {
        self::$restyled[] = $prompt;

        return new GenerationHandle('fake', 'fake-image-'.Str::random(8), ['credits' => 5]);
    }

    public function pollImage(GenerationHandle $handle): GenerationStatus
    {
        if (self::$imageFails) {
            return new GenerationStatus(GenerationStatus::FAILED, error: 'fake_image_failed');
        }
        $path = sys_get_temp_dir().'/mp_fakeimg_'.Str::random(8).'.png';
        $img = imagecreatetruecolor(8, 8);
        imagefill($img, 0, 0, imagecolorallocate($img, 240, 200, 120));
        imagepng($img, $path);
        imagedestroy($img);

        return new GenerationStatus(GenerationStatus::DONE, previewPath: $path, progress: 100);
    }

    public function fromImages(array $views, ?string $hint, GenerationOptions $options): GenerationHandle
    {
        // "credits" = 10 per view, so a test can tell how many sides reached the engine
        return new GenerationHandle('fake', 'fake-'.Str::random(8), ['views' => array_keys($views), 'credits' => 10 * count($views)]);
    }

    public function poll(GenerationHandle $handle): GenerationStatus
    {
        if (self::$glb !== null && is_file(self::$glb)) {
            $glb = sys_get_temp_dir().'/mp_fakegen_'.Str::random(8).'.glb';
            copy(self::$glb, $glb);

            return new GenerationStatus(GenerationStatus::DONE, meshPath: $glb, progress: 100);
        }
        $path = sys_get_temp_dir().'/mp_fakegen_'.Str::random(8).'.stl';
        $fh = StlFile::beginBinary($path);
        $v = [[0, 0, 0], [1, 0, 0], [1, 1, 0], [0, 1, 0], [0, 0, 1], [1, 0, 1], [1, 1, 1], [0, 1, 1]];
        $faces = [[0, 2, 1], [0, 3, 2], [4, 5, 6], [4, 6, 7], [0, 1, 5], [0, 5, 4], [2, 3, 7], [2, 7, 6], [0, 4, 7], [0, 7, 3], [1, 2, 6], [1, 6, 5]];
        foreach ($faces as [$a, $b, $c]) {
            StlFile::writeTriangle($fh, $v[$a], $v[$b], $v[$c]);
        }
        StlFile::endBinary($fh, count($faces));

        return new GenerationStatus(GenerationStatus::DONE, meshPath: $path, progress: 100);
    }
}
