<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Engines\Repair\PythonTool;
use App\Models\Event;
use App\Models\ModelFile;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\QrRaster;
use Tests\TestCase;

/**
 * The QR sign without the technical notation (docs/AI.md): a web address, a Wi-Fi network or a text in a plain form;
 * the server composes what the code carries, and the password of a network stays out of the measurements and the log.
 */
class QrWifiTest extends TestCase
{
    use RefreshDatabase;

    private const SSID = 'Kavárna "U;Dvou:Koček"';

    private const PASSWORD = 'a\\b;c,d:e"f';

    private const EXPECTED = 'WIFI:T:WPA;S:Kavárna \\"U\\;Dvou\\:Koček\\";P:a\\\\b\\;c\\,d\\:e\\"f;;';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The parametric tools need Python with manifold3d and segno.');
        }
    }

    private function preview(array $params, string $part = 'all')
    {
        return $this->postJson('/api/tools/param/preview', ['kind' => 'qr', 'params' => $params, 'part' => $part]);
    }

    /** What a reader sees on the rendered sign; null when no reader is installed (then the test is skipped). */
    private function readCode(array $params): ?string
    {
        $res = $this->preview($params + ['label' => ''])->assertOk();
        $meta = PreviewMeta::whole($res->headers->get('X-Model-Meta'));
        $png = sys_get_temp_dir().'/qr-wifi-'.bin2hex(random_bytes(4)).'.png';
        QrRaster::png(QrRaster::modules($res->baseResponse->getFile()->getPathname(), (float) $meta['notes']['module_mm'], (int) $meta['notes']['modules']), $png);
        $r = app(PythonTool::class)->runScript('qr_decode.py', [$png]);
        @unlink($png);
        if (empty($r['ok']) && str_contains((string) ($r['error'] ?? ''), 'cv2')) {
            return null;
        }
        $this->assertTrue((bool) ($r['ok'] ?? false), 'the sign could not be read: '.json_encode($r));

        return (string) $r['text'];
    }

    public function test_a_network_becomes_the_string_phones_read_with_every_special_character_escaped(): void
    {
        $this->assertSame(self::EXPECTED, ParametricGenerator::wifiString(self::SSID, self::PASSWORD));
        $this->assertSame('WIFI:T:WEP;S:"ABCDEF12";P:"deadbeef";H:true;;', ParametricGenerator::wifiString('ABCDEF12', 'deadbeef', 'wep', true));   // hex-only values in quotes, or a reader takes them for hex
        $this->assertSame('WIFI:T:nopass;S:Open Cafe;;', ParametricGenerator::wifiString('Open Cafe', null, 'nopass'));

        $payload = fn (array $p) => ParametricGenerator::qrPayload(ParametricGenerator::clean('qr', $p));
        $this->assertSame(self::EXPECTED, $payload(['content' => 'wifi', 'ssid' => self::SSID, 'password' => self::PASSWORD]));
        $this->assertSame('WIFI:T:nopass;S:Open;H:true;;', $payload(['content' => 'wifi', 'ssid' => 'Open', 'security' => 'nopass', 'password' => 'ignored', 'hidden' => true]));
        $this->assertSame('https://matplace.com', $payload(['content' => 'web', 'url' => 'matplace.com']));
        $this->assertSame('https://matplace.com/menu?den=po', $payload(['content' => 'web', 'url' => ' https://matplace.com/menu?den=po ']));
        $this->assertSame('Stůl 4', $payload(['content' => 'text', 'text' => ' Stůl 4 ']));

        $bad = [
            [['content' => 'wifi', 'password' => 'x'], 'ssid', 'qr_no_ssid'],
            [['content' => 'wifi', 'ssid' => 'Doma'], 'password', 'qr_no_password'],
            [['content' => 'web', 'url' => 'x'], 'url', 'qr_bad_url'],
            [['content' => 'web', 'url' => 'mailto:a@b.cz'], 'url', 'qr_bad_url'],
            [['content' => 'text', 'text' => ''], 'text', 'qr_no_text'],
            [['content' => 'text', 'text' => str_repeat('a', 70), 'size' => 40], 'text', 'qr_too_long'],
        ];
        foreach ($bad as [$p, $field, $code]) {
            try {
                $payload($p);
                $this->fail("$code was not raised");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('params.'.$field, $e->errors(), $code);
                $this->assertSame(__('param.error.'.$code, ['n' => 62, 'm' => 41]), $e->errors()['params.'.$field][0]);   // 70 bytes need version 5: 45 modules of 0.9 mm
            }
        }
        $this->assertSame(62, ParametricGenerator::qrCapacity(40));          // version 4 at 0.9 mm modules
        $this->assertSame(300, ParametricGenerator::qrCapacity(70));
        $this->assertSame(0, ParametricGenerator::qrCapacity(20));
    }

    public function test_the_code_on_the_rendered_sign_carries_the_network_and_the_address_made_whole(): void
    {
        $read = $this->readCode(['content' => 'wifi', 'ssid' => self::SSID, 'password' => self::PASSWORD, 'size' => 90]);
        if ($read === null) {
            $this->markTestSkipped('OpenCV (cv2) is not installed: the rendered code cannot be read here.');
        }
        $this->assertSame(self::EXPECTED, $read);
        $this->assertSame('https://matplace.com', $this->readCode(['content' => 'web', 'url' => 'matplace.com', 'size' => 60]));
        // a design stored before the kinds of content: the one field it had, as it was typed
        $this->assertSame('WIFI:T:WPA;S:Kavarna;P:dobrakava;;', $this->readCode(['url' => 'WIFI:T:WPA;S:Kavarna;P:dobrakava;;', 'size' => 70]));
    }

    public function test_a_wrong_field_is_refused_at_that_field_and_the_sign_keeps_its_sizes_whatever_the_content(): void
    {
        // the error names the field it is about (params.url, params.ssid…): the page marks that field
        $at = fn (array $p, string $field) => $this->preview($p)->assertStatus(422)->json('errors')['params.'.$field][0] ?? null;
        $this->assertSame(__('param.error.qr_bad_url'), $at(['content' => 'web', 'url' => 'x'], 'url'));
        $this->assertSame(__('param.error.qr_no_ssid'), $at(['content' => 'wifi', 'ssid' => '', 'password' => 'x'], 'ssid'));
        $this->assertSame(__('param.error.qr_no_password'), $at(['content' => 'wifi', 'ssid' => 'Doma', 'password' => ''], 'password'));
        $this->assertSame(__('param.error.qr_too_long', ['n' => 62, 'm' => 41]), $at(['content' => 'text', 'text' => str_repeat('a', 70), 'size' => 40], 'text'));
        $this->assertSame(41, ParametricGenerator::qrSizeFor(70));
        $this->assertSame(30, ParametricGenerator::qrSizeFor(20));
        $this->assertNull(ParametricGenerator::qrSizeFor(3000));
        $this->preview(['content' => 'wifi', 'ssid' => 'Doma', 'security' => 'nopass'])->assertOk();

        // the kind of content changes nothing but the code: the same plate, stand, hole, colours and caption
        $web = PreviewMeta::whole($this->preview(['content' => 'web', 'url' => 'matplace.com', 'size' => 80, 'stand' => true, 'label' => 'Menu'])->assertOk()->headers->get('X-Model-Meta'));
        $wifi = PreviewMeta::whole($this->preview(['content' => 'wifi', 'ssid' => 'Doma', 'password' => 'tajne123', 'size' => 80, 'stand' => true, 'label' => 'Menu'])->assertOk()->headers->get('X-Model-Meta'));
        $this->assertSame($web['notes']['outer'], $wifi['notes']['outer']);
        $this->assertSame($web['notes']['colors'], $wifi['notes']['colors']);
        $this->assertSame($web['parts'] ?? null, $wifi['parts'] ?? null);
        $this->assertTrue($wifi['notes']['verified']);
        // nothing the code carries goes into the notes the page gets
        $this->assertStringNotContainsString('tajne123', json_encode($wifi));
        $this->assertStringNotContainsString('WIFI:', json_encode($wifi));
    }

    public function test_the_password_reaches_neither_the_measurements_nor_the_log_nor_the_file_name(): void
    {
        $log = sys_get_temp_dir().'/qr-wifi-'.bin2hex(random_bytes(4)).'.log';
        config(['logging.default' => 'single', 'logging.channels.single.path' => $log, 'logging.channels.single.level' => 'debug']);
        $password = 'TajneHeslo-9x';
        $ssid = 'Kavarna U Dvou Kocek';

        $this->preview(['content' => 'wifi', 'ssid' => $ssid, 'password' => $password])->assertOk();
        $created = $this->postJson('/api/tools/param', ['kind' => 'qr', 'params' => ['content' => 'wifi', 'ssid' => $ssid, 'password' => $password, 'label' => 'Wi-Fi']])->assertCreated();
        $file = ModelFile::where('uuid', $created->json('file.uuid'))->firstOrFail();
        $this->getJson('/api/files/'.$file->uuid)->assertOk();

        $this->assertMatchesRegularExpression('/^qr-\d+x\d+x\d+\.stl$/', $file->original_name);   // the sizes, nothing of the content
        $this->assertSame($password, $file->tool_params['password']);                  // the design itself keeps it: that is how it opens again
        foreach (Event::all() as $event) {
            $this->assertStringNotContainsString($password, json_encode($event->getAttributes()), 'event '.$event->type);
            $this->assertStringNotContainsString($ssid, json_encode($event->getAttributes()), 'event '.$event->type);
        }
        $this->assertStringNotContainsString($password, is_file($log) ? (string) file_get_contents($log) : '');
        @unlink($log);
    }

    public function test_the_page_offers_the_three_kinds_of_content_in_every_language_without_raw_keys(): void
    {
        foreach (['/tools/qr', '/en/tools/qr', '/es/tools/qr'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            foreach (['web', 'wifi', 'text'] as $kind) {
                $this->assertStringContainsString('data-choice="content" value="'.$kind.'"', $html, $path);
            }
            $this->assertStringContainsString('data-text="ssid"', $html, $path);
            $this->assertStringContainsString('data-text="password" type="password"', $html, $path);
            $this->assertStringContainsString('data-reveal="password"', $html, $path);
            $this->assertStringContainsString('data-flag="hidden"', $html, $path);
            $this->assertMatchesRegularExpression('/data-when="content=wifi"[^>]*>[^<]*<input data-text="ssid"/', $html, $path);
            $this->assertMatchesRegularExpression('/data-when="security=wpa,wep"/', $html, $path);
            $this->assertStringNotContainsString('WIFI:T:', $html, $path);              // the technical notation is gone from the page
            // (the page's own dictionary for the script names keys like param.qr.facts: only the labels are checked for raw keys)
            foreach (['param.o.qr.', 'param.c.qr.', 'param.t.qr.', 'param.flag.qr.', 'param.qr.text.hint', 'param.qr.lead'] as $raw) {
                $this->assertStringNotContainsString($raw, $html, "$path shows the raw key $raw");
            }
        }
    }
}
