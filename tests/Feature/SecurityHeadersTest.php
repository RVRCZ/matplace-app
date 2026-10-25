<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Response headers (config/security.php, TODO #45): the policy in its three modes, and the report endpoint. */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_carry_the_policy_as_report_only_and_the_permissions_policy(): void
    {
        config(['security.csp.mode' => 'report']);
        $r = $this->get('/');
        $r->assertOk();
        $csp = (string) $r->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString('script-src \'self\' \'unsafe-inline\' https://www.googletagmanager.com', $csp);
        $this->assertStringContainsString('report-uri /csp-report; report-to csp', $csp);
        $this->assertStringContainsString('csp="', (string) $r->headers->get('Reporting-Endpoints'));
        $this->assertStringContainsString('camera=(self)', (string) $r->headers->get('Permissions-Policy'));
        // report-only blocks nothing: the enforcing header and the frame header stay away
        $this->assertFalse($r->headers->has('Content-Security-Policy'));
        $this->assertFalse($r->headers->has('X-Frame-Options'));
    }

    public function test_the_enforced_mode_sends_the_real_header_and_the_frame_header(): void
    {
        config(['security.csp.mode' => 'enforce']);
        $r = $this->get('/tools');
        $r->assertOk();
        $this->assertStringContainsString("object-src 'none'", (string) $r->headers->get('Content-Security-Policy'));
        $this->assertFalse($r->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'));
    }

    public function test_switched_off_the_pages_keep_only_the_permissions_policy(): void
    {
        config(['security.csp.mode' => 'off']);
        $r = $this->get('/');
        $r->assertOk();
        $this->assertFalse($r->headers->has('Content-Security-Policy'));
        $this->assertFalse($r->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertTrue($r->headers->has('Permissions-Policy'));
    }

    public function test_a_json_answer_has_no_policy(): void
    {
        config(['security.csp.mode' => 'enforce']);
        $r = $this->getJson('/api/config');
        $r->assertOk();
        $this->assertFalse($r->headers->has('Content-Security-Policy'));
    }

    public function test_a_report_is_logged_once_an_hour_and_counted_after(): void
    {
        $report = ['csp-report' => [
            'document-uri' => url('/tools/qr').'?x=secret', 'violated-directive' => 'script-src-elem', 'effective-directive' => 'script-src-elem',
            'blocked-uri' => 'https://evil.example/x.js', 'source-file' => url('/build/assets/app.js'), 'line-number' => 12, 'disposition' => 'report',
        ]];
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode($report))->assertNoContent();
        $key = 'csp:'.sha1(implode('|', ['/tools/qr', 'script-src-elem', 'https://evil.example/x.js', '/build/assets/app.js:12']));
        $this->assertSame(1, Cache::get($key));
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode($report))->assertNoContent();
        $this->assertSame(2, Cache::get($key));
    }

    public function test_the_reporting_api_shape_and_extension_noise(): void
    {
        $reports = [
            ['type' => 'csp-violation', 'body' => ['documentURL' => url('/'), 'effectiveDirective' => 'img-src', 'blockedURL' => 'chrome-extension://abc/x.png']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => url('/'), 'effectiveDirective' => 'img-src', 'blockedURL' => 'https://cdn.example/a.png', 'sourceFile' => '', 'lineNumber' => 0]],
        ];
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/reports+json'], json_encode($reports))->assertNoContent();
        $this->assertNull(Cache::get('csp:'.sha1(implode('|', ['/', 'img-src', 'chrome-extension://abc/x.png', ':0']))));
        $this->assertSame(1, Cache::get('csp:'.sha1(implode('|', ['/', 'img-src', 'https://cdn.example/a.png', ':0']))));
        // rubbish is answered quietly
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], 'not json')->assertNoContent();
    }
}
