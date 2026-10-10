<?php

namespace App\Http\Middleware;

use App\Domain\Tools\ToolVisibility;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tool that is not public (switched off at /admin/tools, or held back in config/tools.php) answers 404: its page,
 * and the API its page works with. An admin gets through, and the layout then says so on the page (`hidden_tool`).
 * The calculator is the home page: switching it off takes its card out of the catalogue, the page stays.
 */
class ToolGate
{
    private const PARAM = ['api.tools.param', 'api.tools.param.preview', 'api.tools.param.zip'];

    private const ART = ['api.tools.art', 'api.tools.art.preview', 'api.tools.art.zip'];

    private const EDIT = ['api.files.edit', 'api.files.edit.analysis'];

    public function handle(Request $request, Closure $next): Response
    {
        $name = Locales::baseName((string) $request->route()?->getName());
        $user = $request->user();
        $asked = fn (string $field) => is_string($request->input($field)) ? $request->input($field) : '';

        if (str_starts_with($name, 'api.')) {
            abort_unless(match (true) {
                in_array($name, self::PARAM, true) => ToolVisibility::canUseKind($user, $asked('kind')),
                $name === 'api.tools.sign' => ToolVisibility::canUseKind($user, 'sign'),
                in_array($name, self::ART, true) => ToolVisibility::canOpen($user, 'filament_art'),
                $name === 'api.tools.relief' => ToolVisibility::canOpen($user, 'relief'),
                in_array($name, self::EDIT, true) => ToolVisibility::canUseEdit($user, $asked('op')),
                default => true,
            }, 404);

            return $next($request);
        }

        $tool = ToolVisibility::ofRoute($name);
        if ($tool !== null && $tool !== 'calc' && ! ToolVisibility::isPublic($tool)) {
            abort_unless(ToolVisibility::canOpen($user, $tool), 404);
            $request->attributes->set('hidden_tool', $tool);
        }

        return $next($request);
    }
}
