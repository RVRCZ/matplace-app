<?php

namespace App\Domain\Tools;

use App\Models\ToolFlag;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Who sees a tool. A tool is public when config/tools.php offers it (`available`) and the admin has not switched it
 * off at /admin/tools (table tool_flags; no row = the config alone decides). A tool that is not public is in no list,
 * no sitemap and no link, and its page and API answer 404; an admin still opens it, to try it before it goes out.
 *
 * The switches are read once a request and kept in the cache for a minute; saving one clears both.
 */
final class ToolVisibility
{
    public const CACHE_KEY = 'tool_flags';

    public const CACHE_SECONDS = 60;

    /** @var array<string, bool>|null tool → public, as the admin set it */
    private ?array $flags = null;

    /** @var array{kind: array<string, list<string>>, op: array<string, list<string>>}|null */
    private ?array $users = null;

    public static function isPublic(string $tool): bool
    {
        return ! empty(config('tools')[$tool]['available']) && (self::state()->flags()[$tool] ?? true);
    }

    /** The page and the API of a tool: everybody for a public one, an admin for any. */
    public static function canOpen(?User $user, string $tool): bool
    {
        return self::isPublic($tool) || (bool) $user?->isAdmin();
    }

    /** @return array<string, array<string, mixed>> the tools on offer, as config/tools.php lists them */
    public static function listed(): array
    {
        return array_filter((array) config('tools'), fn (string $tool) => self::isPublic($tool), ARRAY_FILTER_USE_KEY);
    }

    /** The tool whose page a route is (the name with or without the language prefix), null for any other page. */
    public static function ofRoute(?string $name): ?string
    {
        $name = Locales::baseName($name);
        foreach ((array) config('tools') as $key => $tool) {
            if ($name !== '' && ($tool['route'] ?? null) === $name) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * A generator (`kind` of /api/tools/param) serves every tool whose page is built on it: the logo tool and SVG to
     * STL, the composer and the nameplate. It stays open while one of them is open to this visitor.
     */
    public static function canUseKind(?User $user, string $kind): bool
    {
        return self::opensOne($user, self::state()->users()['kind'][$kind] ?? []);
    }

    /** The same for an edit of a model file (`op` of /api/files/{uuid}/edit): split, hollow, life size… */
    public static function canUseEdit(?User $user, string $op): bool
    {
        return self::opensOne($user, self::state()->users()['op'][$op] ?? []);
    }

    /** After a switch was saved: the next question reads the table again. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::state()->flags = null;
    }

    /** @param  list<string>  $tools  nothing built on it = not a matter of the catalogue, open */
    private static function opensOne(?User $user, array $tools): bool
    {
        foreach ($tools as $tool) {
            if (self::canOpen($user, $tool)) {
                return true;
            }
        }

        return $tools === [];
    }

    private static function state(): self
    {
        return app(self::class);
    }

    /** @return array<string, bool> */
    private function flags(): array
    {
        if ($this->flags === null) {
            try {
                $this->flags = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => ToolFlag::query()->pluck('public', 'tool')->map(fn ($public) => (bool) $public)->all());
            } catch (\Throwable) {
                $this->flags = [];      // no table yet (the code is here before its migration): the config alone decides
            }
        }

        return $this->flags;
    }

    /** Which tools work with which generator and which edit: the routes of the tools' pages say it. */
    private function users(): array
    {
        if ($this->users === null) {
            $this->users = ['kind' => [], 'op' => []];
            foreach ((array) config('tools') as $key => $tool) {
                $given = Route::getRoutes()->getByName((string) ($tool['route'] ?? ''))?->defaults ?? [];
                // a page that became the composer keeps its quick form, drawn by the generator it used to be ('sign:shaped')
                $kinds = array_filter([$given['kind'] ?? null, explode(':', (string) ($given['form'] ?? ''))[0]]);
                foreach (array_unique($kinds) as $kind) {
                    $this->users['kind'][$kind][] = (string) $key;
                }
                if (isset($given['op'])) {
                    $this->users['op'][$given['op']][] = (string) $key;
                }
            }
        }

        return $this->users;
    }
}
