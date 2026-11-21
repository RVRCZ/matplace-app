<?php

namespace Tests\Feature;

use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * A sound binary STL is stored once (20 Nov 2026: the second copies took 13 GB of the server's 75 GB): the model
 * reads its original; the copies of older models are removed by matplace:dedupe-stl.
 */
class StlStoredOnceTest extends TestCase
{
    use RefreshDatabase;

    private function upload(): ModelFile
    {
        $path = sys_get_temp_dir().'/mp_once_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');

        return ModelFile::where('uuid', $uuid)->firstOrFail();
    }

    public function test_a_binary_stl_is_not_copied_and_is_still_served(): void
    {
        $file = $this->upload();
        $disk = Storage::disk(ModelFile::DISK);
        $this->assertSame(ModelFile::STATUS_READY, $file->status);
        $this->assertSame($file->storage_path, $file->stl_path);
        $this->assertFalse($disk->exists($file->dir().'/model.stl'));
        $this->assertEqualsWithDelta(8000.0, $file->volume_mm3, 0.01);
        $this->get('/api/files/'.$file->uuid.'/model.stl')->assertOk()->assertHeader('Content-Type', 'model/stl');
    }

    public function test_the_command_removes_only_copies_that_are_the_same(): void
    {
        $disk = Storage::disk(ModelFile::DISK);
        // two models stored the old way; the second one's model.stl differs from its original
        $same = $this->upload();
        $other = $this->upload();
        foreach ([$same, $other] as $f) {
            $disk->copy($f->storage_path, $f->dir().'/model.stl');
            ModelFile::whereKey($f->id)->update(['stl_path' => $f->dir().'/model.stl', 'updated_at' => now()->subDay()]);
        }
        $disk->append($other->dir().'/model.stl', 'x');
        // a third one, just touched: left for the next run
        $fresh = $this->upload();
        $disk->copy($fresh->storage_path, $fresh->dir().'/model.stl');
        ModelFile::whereKey($fresh->id)->update(['stl_path' => $fresh->dir().'/model.stl']);

        $this->artisan('matplace:dedupe-stl', ['--dry-run' => true])->assertSuccessful();
        $this->assertTrue($disk->exists($same->dir().'/model.stl'));

        $this->artisan('matplace:dedupe-stl')->assertSuccessful();
        $this->assertFalse($disk->exists($same->dir().'/model.stl'));
        $this->assertSame($same->storage_path, $same->fresh()->stl_path);
        $this->assertTrue($disk->exists($same->storage_path));
        $this->assertTrue($disk->exists($other->dir().'/model.stl'));
        $this->assertSame($other->dir().'/model.stl', $other->fresh()->stl_path);
        $this->assertTrue($disk->exists($fresh->dir().'/model.stl'));
        $this->get('/api/files/'.$same->uuid.'/model.stl')->assertOk();
    }
}
