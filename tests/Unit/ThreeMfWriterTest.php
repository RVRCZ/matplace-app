<?php

namespace Tests\Unit;

use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\ThreeMfWriter;
use PHPUnit\Framework\TestCase;

/**
 * The project the farm hands the slicer (docs/AL.md §10, §10.13, §11.10): an object per part; with instances, a build
 * item per copy and object, every item the copy's move and turn, every object the same list – so Orca slices each
 * object once and prints it wherever its items stand, and the parts of one piece stay set in each other.
 */
final class ThreeMfWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/mp_3mf_'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        StlFile::writeBox($this->dir.'/body.stl', 20, 20, 3);
        StlFile::writeBox($this->dir.'/inlay.stl', 6, 6, 0.6);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** @return array{0: string, 1: list<array{0: string, 1: string}>} the model XML and its build items as [objectid, transform] */
    private function items(string $path): array
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'the project opens');
        $model = (string) $zip->getFromName('3D/3dmodel.model');
        $zip->close();
        preg_match_all('/<item objectid="(\d+)" transform="([^"]+)"\/>/', $model, $m, PREG_SET_ORDER);

        return [$model, array_map(fn ($x) => [$x[1], $x[2]], $m)];
    }

    private function objects(): array
    {
        return [['name' => 'body', 'stl' => $this->dir.'/body.stl', 'extruder' => 1], ['name' => 'inlay', 'stl' => $this->dir.'/inlay.stl', 'extruder' => 2]];
    }

    public function test_without_instances_every_object_stands_once_where_it_is(): void
    {
        ThreeMfWriter::write($this->dir.'/one.3mf', $this->objects());
        [$model, $items] = $this->items($this->dir.'/one.3mf');
        $this->assertSame(2, substr_count($model, '<object id='));
        $this->assertSame([['1', '1 0 0 0 1 0 0 0 1 0 0 0'], ['2', '1 0 0 0 1 0 0 0 1 0 0 0']], $items);
    }

    public function test_instances_place_every_object_once_per_copy_with_the_same_moves_and_turns(): void
    {
        // three copies: as it is, 25 mm to the right turned a quarter, 25 mm up and 2.5 mm raised
        ThreeMfWriter::write($this->dir.'/copies.3mf', $this->objects(), [], [[0.0, 0.0], [25.0, 0.0, 0.0, 1], [0.0, 25.0, 2.5]]);
        [$model, $items] = $this->items($this->dir.'/copies.3mf');
        $this->assertSame(2, substr_count($model, '<object id='), 'the mesh of a part is in the file once, however many copies');
        $this->assertCount(6, $items);
        $expected = ['1 0 0 0 1 0 0 0 1 0 0 0', '0 1 0 -1 0 0 0 0 1 25 0 0', '1 0 0 0 1 0 0 0 1 0 25 2.5'];
        $this->assertSame($expected, array_column(array_slice($items, 0, 3), 1), 'the body: unit, a quarter turn about Z moved right, moved up and raised');
        $this->assertSame(['1', '1', '1', '2', '2', '2'], array_column($items, 0));
        $this->assertSame($expected, array_column(array_slice($items, 3), 1), 'the inlay gets the very same list, so it stays set in its body');
    }

    public function test_the_transform_turns_about_z_the_way_the_plate_layout_turns_a_copy(): void
    {
        // libslic3r reads the twelve numbers transposed: the first three are the image of x, the next three of y, the last three the move
        $this->assertSame('1 0 0 0 1 0 0 0 1 1.5 -2 0', ThreeMfWriter::transform([1.5, -2.0]));
        $this->assertSame('0 1 0 -1 0 0 0 0 1 10 20 0', ThreeMfWriter::transform([10.0, 20.0, 0.0, 1]), '(x, y) → (−y, x)');
        $this->assertSame('-1 0 0 0 -1 0 0 0 1 0 0 0', ThreeMfWriter::transform([0.0, 0.0, 0.0, 2]), 'half a turn');
        $this->assertSame('0 -1 0 1 0 0 0 0 1 0 0 3', ThreeMfWriter::transform([0.0, 0.0, 3.0, 3]), '(x, y) → (y, −x)');
        $this->assertSame('1 0 0 0 1 0 0 0 1 0 0 0', ThreeMfWriter::transform([0.0, -0.00001, 0.0, 4]), 'four quarters are none, a hair below zero is zero');
    }

    public function test_the_same_content_gives_the_same_bytes(): void
    {
        ThreeMfWriter::write($this->dir.'/a.3mf', $this->objects(), [], [[0.0, 0.0], [30.0, 0.0]]);
        sleep(1);
        ThreeMfWriter::write($this->dir.'/b.3mf', $this->objects(), [], [[0.0, 0.0], [30.0, 0.0]]);
        $this->assertSame(md5_file($this->dir.'/a.3mf'), md5_file($this->dir.'/b.3mf'), 'the zip entries carry a fixed time: the slice cache can hash the project');
        ThreeMfWriter::write($this->dir.'/c.3mf', $this->objects(), [], [[0.0, 0.0], [35.0, 0.0]]);
        $this->assertNotSame(md5_file($this->dir.'/a.3mf'), md5_file($this->dir.'/c.3mf'), 'another gap is another project');
    }
}
