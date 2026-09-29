<?php

namespace Tests\Feature;

use App\Domain\Farm\TimelapseFrames;
use App\Domain\Farm\TimelapseGcode;
use App\Jobs\BuildFarmTimelapse;
use App\Models\FarmAgent;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Layer-synced time-lapse: the head parks after every layer, the agent sends those pictures, the square Short is built from them. */
class FarmLayerTimelapseTest extends TestCase
{
    use RefreshDatabase;

    /** What OrcaSlicer writes around a layer change (Kobra S1 profile, relative extrusion). */
    private const ORCA = <<<'GCODE'
G90
M83 ; use relative distances for extrusion
;LAYER_CHANGE
;Z:0.2
;HEIGHT:0.2
; AFTER_LAYER_CHANGE 1 @ 0.2mm
G1 X95.44 Y96.49 Z.6 F18000
G1 Z.2
G1 E1.2 F2400
G1 X100 Y100 E.5 F3000
;LAYER_CHANGE
;Z:0.4
;HEIGHT:0.2

G1 E-.86667 F2400
;WIPE_START
G1 F7200
G1 X88.276 Y93.08 E-.03193
;WIPE_END
; AFTER_LAYER_CHANGE 2 @ 0.4mm
G1 X95.44 Y96.49 Z.8 F18000
G1 Z.4
G1 E1.2 F2400
G1 X110 Y100 E.5 F3000
;LAYER_CHANGE
;Z:0.6
;HEIGHT:0.2
; AFTER_LAYER_CHANGE 3 @ 0.6mm
G1 X95.44 Y96.49 Z1 F18000
GCODE;

    public function test_the_head_parks_after_every_printed_layer_and_the_slicers_next_travel_takes_over(): void
    {
        $out = TimelapseGcode::apply(self::ORCA, ['park_x' => 240, 'park_y' => 250, 'dwell_ms' => 1200, 'lift_mm' => 0.6, 'travel_mm_s' => 200]);

        $this->assertSame(['park_x' => 240.0, 'park_y' => 250.0, 'dwell_ms' => 1200, 'travel_mm_s' => 200, 'frames' => 2], TimelapseGcode::header($out));
        // not before the first layer: nothing is printed yet
        $this->assertStringContainsString("; AFTER_LAYER_CHANGE 1 @ 0.2mm\nG1 X95.44 Y96.49 Z.6 F18000", $out);
        // after layer 1 the slicer had retracted and wiped: straight up and away, its feed rate put back afterwards
        $this->assertStringContainsString("; AFTER_LAYER_CHANGE 2 @ 0.4mm\n; matplace timelapse frame\nG1 Z0.800 F900\nG1 X240 Y250 F12000\nG4 P1200\nG1 F7200\nG1 X95.44 Y96.49 Z.8 F18000", $out);
        // after layer 2 nothing was retracted: our own retract and unretract around the stop
        $this->assertStringContainsString("; AFTER_LAYER_CHANGE 3 @ 0.6mm\n; matplace timelapse frame\nG1 E-0.8 F2400\nG1 Z1.000 F900\nG1 X240 Y250 F12000\nG4 P1200\nG1 E0.8 F2400\nG1 F3000\nG1 X95.44 Y96.49 Z1 F18000", $out);
        $this->assertSame($out, TimelapseGcode::apply($out, ['park_x' => 1, 'park_y' => 1]), 'applied once only');
    }

    public function test_parking_time_is_counted_per_stop(): void
    {
        // 2 stops, 170 mm from the middle of a 250 mm plate to (240, 250): 1.2 s dwell + 1.7 s there and back + lift ≈ 3.3 s each
        $this->assertSame(1, TimelapseGcode::extraMinutes(self::ORCA, ['park_x' => 240, 'park_y' => 250, 'dwell_ms' => 1200, 'lift_mm' => 0.6, 'travel_mm_s' => 200], 250, 250));
        $many = str_repeat('; AFTER_LAYER_CHANGE
G1 X1 Y1 E.1
', 301);    // 300 stops ≈ 16.4 min
        $this->assertSame(17, TimelapseGcode::extraMinutes('G90
M83
'.$many, ['park_x' => 240, 'park_y' => 250, 'dwell_ms' => 1200], 250, 250));
        $this->assertSame(0, TimelapseGcode::extraMinutes('M82
'.$many, ['park_x' => 240, 'park_y' => 250], 250, 250));
    }

    public function test_a_new_printer_starts_with_the_time_lapse_on_an_existing_one_without_a_setting_stays_off(): void
    {
        $this->assertSame('consent', (new FarmPrinter(['bed_x' => 250, 'bed_y' => 250]))->timelapseSettings()['mode']);
        $saved = new FarmPrinter(['bed_x' => 250, 'bed_y' => 250]);
        $saved->exists = true;
        $this->assertSame('off', $saved->timelapseSettings()['mode']);
    }

    public function test_a_huge_gcode_is_rewritten_line_by_line_without_loading_it(): void
    {
        $path = sys_get_temp_dir().'/mp_big_'.uniqid().'.gcode';
        $fh = fopen($path, 'wb');
        fwrite($fh, 'G90
M83
');
        $layer = '; AFTER_LAYER_CHANGE
'.str_repeat('G1 X10.123 Y20.456 E.01234 ; a move with a comment
', 2000);
        for ($i = 0; $i < 400; $i++) {     // ~40 MB
            fwrite($fh, $layer);
        }
        fclose($fh);
        $before = memory_get_usage();
        $out = TimelapseGcode::fileFor($path, ['park_x' => 250, 'park_y' => 250]);
        $this->assertLessThan(8 * 1024 * 1024, memory_get_peak_usage() - $before, 'memory does not grow with the file');
        $head = fopen($out, 'rb');
        $this->assertSame(399, TimelapseGcode::header((string) fgets($head))['frames']);
        fclose($head);
        $this->assertSame(399, TimelapseGcode::header('; matplace timelapse park_x=250 park_y=250 dwell=1000 travel=200 frames=399')['frames']);
        $this->assertGreaterThan(filesize($path), filesize($out));
        $this->assertSame(TimelapseGcode::extraMinutesForFile($path, ['park_x' => 250, 'park_y' => 250], 250, 250),
            TimelapseGcode::extraMinutes((string) file_get_contents($path), ['park_x' => 250, 'park_y' => 250], 250, 250));
        @unlink($path);
        @unlink($out);
    }

    public function test_absolute_extrusion_or_a_file_without_markers_is_left_alone(): void
    {
        $this->assertSame("M82\n".self::ORCA, TimelapseGcode::apply("M82\n".self::ORCA, ['park_x' => 1, 'park_y' => 1]));
        $plain = "G90\nG1 X1 Y1\n";
        $this->assertSame($plain, TimelapseGcode::apply($plain, ['park_x' => 1, 'park_y' => 1]));
        $this->assertNull(TimelapseGcode::header($plain));
    }

    public function test_agent_gets_the_parking_gcode_only_when_the_printer_and_the_order_want_it_and_layer_frames_are_kept(): void
    {
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        [$printer, $auth] = $this->agentPrinter();
        $this->postJson('/api/agent/sync', ['version' => 'test', 'printers' => [['key' => $printer->key, 'state' => 'idle', 'telemetry' => [], 'job' => null]]], $auth)->assertOk();
        $order = $this->paidOrder($user);
        Storage::disk('farm')->put($order->gcode_path, self::ORCA);
        $printer->update(['timelapse' => ['mode' => 'consent', 'park_x' => 240, 'park_y' => 250]]);

        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->post("/admin/farm/printers/{$printer->id}/bed", ['clear' => 1])->assertRedirect();
        $cmd = $this->postJson('/api/agent/sync', ['version' => 'test', 'printers' => [['key' => $printer->key, 'state' => 'idle', 'telemetry' => [], 'job' => null]]], $auth)
            ->assertOk()->json('commands.0');
        $job = FarmPrintJob::findOrFail($cmd['job_id']);

        // mode "consent", no consent: the ordinary G-code
        $this->assertNull(TimelapseGcode::header($this->get($cmd['payload']['gcode_url'], $auth)->assertOk()->streamedContent()));
        // the customer agrees: the head parks after every layer
        $order->forceFill(['video_consent' => true])->save();
        $gcode = $this->get($cmd['payload']['gcode_url'], $auth)->assertOk();
        $this->assertSame(2, TimelapseGcode::header($gcode->streamedContent())['frames']);
        $this->assertSame(hash('sha256', $gcode->streamedContent()), $gcode->headers->get('X-Content-Sha256'));

        // every layer picture is kept, the ordinary ones one a minute
        $jpeg = fn () => UploadedFile::fake()->image('s.jpg', 64, 48);
        foreach ([1, 2, 3] as $i) {
            $this->post("/api/agent/printers/{$printer->key}/snapshot", ['image' => $jpeg(), 'job_id' => $job->id, 'frame' => 'layer'], $auth)->assertOk();
        }
        $this->post("/api/agent/printers/{$printer->key}/snapshot", ['image' => $jpeg(), 'job_id' => $job->id], $auth)->assertOk();
        $this->post("/api/agent/printers/{$printer->key}/snapshot", ['image' => $jpeg(), 'job_id' => $job->id], $auth)->assertOk();
        $this->assertCount(3, Storage::disk('farm')->files($order->dir().'/frames_layer'));
        $this->assertCount(1, Storage::disk('farm')->files($order->dir().'/frames'));

        // an admin setting: the manual (non-agent) printer never parks, a test print never parks
        $this->assertNull((clone $printer)->forceFill(['mode' => FarmPrinter::MODE_MANUAL])->timelapseFor($order));
        $this->assertNull($printer->timelapseFor((clone $order)->forceFill(['kind' => FarmOrder::KIND_TEST])));
    }

    public function test_layer_frames_become_the_timelapse_and_a_square_short(): void
    {
        $ffmpeg = (string) env('FFMPEG_BIN', '');
        if ($ffmpeg === '' || ! is_file($ffmpeg) && Process::run([$ffmpeg, '-version'])->failed()) {
            $this->markTestSkipped('FFMPEG_BIN not set: the video build needs a real ffmpeg');
        }
        config(['farm.ffmpeg' => $ffmpeg]);
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $order = $this->paidOrder(User::factory()->create());
        $order->forceFill(['status' => FarmOrder::STATUS_DONE])->save();

        $disk = Storage::disk('farm');
        $t = 1_790_000_000;
        for ($i = 0; $i < 30; $i++) {            // 30 layers, the object grows
            $disk->put(sprintf('%s/frames_layer/%.3f.jpg', $order->dir(), $t + $i * 20.5), $this->picture($i * 8));
        }
        $disk->put($order->dir().'/frames/'.($t - 60).'.jpg', $this->picture(0));
        $disk->put($order->dir().'/frames/'.($t + 1000).'.jpg', $this->picture(240));     // the finished piece, head gone
        (new BuildFarmTimelapse($order->id))->handle();

        $order->refresh();
        $this->assertSame($order->dir().'/timelapse.mp4', $order->timelapse_path);
        $this->assertSame($order->dir().'/short.mp4', $order->timelapse_short_path);
        $this->assertCount(30, $disk->files($order->dir().'/frames_layer'), 'frames stay for a rebuild (matplace:prune removes them later)');
        $this->artisan('farm:timelapse', ['number' => $order->number])->assertSuccessful();
        $info = Process::run([$ffmpeg, '-hide_banner', '-i', $disk->path($order->timelapse_short_path)])->errorOutput();
        $this->assertMatchesRegularExpression('/Video: h264.*\b720x720\b/', $info, 'a square Short');
    }

    public function test_pictures_that_caught_the_head_printing_are_left_out(): void
    {
        $dir = sys_get_temp_dir().'/mp_strays_'.uniqid();
        mkdir($dir);
        $paths = [];
        for ($i = 0; $i < 30; $i++) {
            $path = sprintf('%s/%03d.jpg', $dir, $i);
            // the head over the object on three pictures, parked (outside this picture) on the rest
            file_put_contents($path, $this->picture($i * 3, in_array($i, [5, 17, 18], true)));
            $paths[] = $path;
        }
        $this->assertSame([5, 17, 18], TimelapseFrames::strays($paths));
        array_map('unlink', $paths);
        rmdir($dir);
    }

    /** A 1280×720 camera picture with a bar of the given height: something that grows. */
    private function picture(int $height, bool $head = false): string
    {
        $im = imagecreatetruecolor(1280, 720);
        imagefill($im, 0, 0, imagecolorallocate($im, 40, 40, 40));
        imagefilledrectangle($im, 560, 600 - $height, 720, 600, imagecolorallocate($im, 201, 71, 20));
        if ($head) {
            imagefilledrectangle($im, 480, 0, 800, 380, imagecolorallocate($im, 235, 235, 235));
        }
        ob_start();
        imagejpeg($im, null, 80);

        return (string) ob_get_clean();
    }

    private function agentPrinter(): array
    {
        [$agent, $token] = FarmAgent::issue('test agent');
        $printer = FarmPrinter::firstOrFail();
        $printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id]);

        return [$printer, ['Authorization' => 'Bearer '.$token]];
    }

    private function paidOrder(User $user): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_tl_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $order = FarmOrder::where('token', basename($this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url')))->firstOrFail();
        $this->actingAs($user)->post('/account/credit', ['amount' => 1000])->assertRedirect();
        $this->postJson('/webhooks/payments/fake', ['ref' => Payment::latest('id')->firstOrFail()->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $state = $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/pay", [
            'slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total'],
        ])->assertOk();

        return $order->refresh();
    }
}
