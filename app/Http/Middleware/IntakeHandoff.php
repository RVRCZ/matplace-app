<?php

namespace App\Http\Middleware;

use App\Domain\Intake\IntakeService;
use App\Domain\Tools\ToolVisibility;
use App\Models\Intake;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tool's page opened from the home page's router (`/tools/<key>?intake=<token>`, TODO #78, docs/AN.md §22): the
 * intake behind the token is handed to the page (request attribute `intake`, read by tools/page.blade.php into
 * window.MP_TOOL.intake), so the page's script fetches the photo and fills the parameters in; the intake is marked
 * as opened. A token nobody knows, or a page that is no tool, changes nothing.
 */
class IntakeHandoff
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('intake');
        if ($request->isMethod('GET') && is_string($token) && preg_match('/^[A-Za-z0-9]{24}$/', $token)) {
            $tool = ToolVisibility::ofRoute(Locales::baseName((string) $request->route()?->getName()));
            if ($tool !== null && $tool !== 'calc' && ($intake = Intake::where('token', $token)->first())) {
                $request->attributes->set('intake', app(IntakeService::class)->handoff($intake, $tool));
            }
        }

        return $next($request);
    }
}
