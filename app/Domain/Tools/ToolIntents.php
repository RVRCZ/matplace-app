<?php

namespace App\Domain\Tools;

/**
 * The tools behind each intent of the catalogue (config/tool_intents.php): a gift, from a photo, the home, a spare part,
 * for a business. Resolved from the tools on offer, so a tool switched off or not yet on the site never makes an empty
 * chip; an intent with nothing behind it is left out.
 */
final class ToolIntents
{
    /**
     * @param  array<string, array>  $listed  the tools on offer, keyed by tool (ToolVisibility::listed() after the page's own filter)
     * @return array<string, list<string>>  intent → the keys of its tools, in the order of the catalogue
     */
    public static function all(array $listed): array
    {
        $out = [];
        foreach ((array) config('tool_intents', []) as $intent => $def) {
            $keys = self::tools((string) $intent, $listed);
            if ($keys) {
                $out[$intent] = $keys;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function tools(string $intent, array $listed): array
    {
        $def = (array) (config('tool_intents.'.$intent) ?? []);
        if (! $def) {
            return [];
        }
        $cats = (array) ($def['cats'] ?? []);
        $inputs = (array) ($def['inputs'] ?? []);
        $named = (array) ($def['tools'] ?? []);
        $keys = [];
        foreach ($listed as $key => $tool) {
            $key = (string) $key;
            if (in_array($key, $named, true)
                || ($cats && array_intersect($cats, (array) ($tool['categories'] ?? [])))
                || ($inputs && in_array(ToolInput::of($key, (array) $tool), $inputs, true))) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
