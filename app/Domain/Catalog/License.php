<?php

namespace App\Domain\Catalog;

/**
 * The licence a source names in its own words → the key the catalogue keeps (lang models.license.<key>).
 * What cannot be recognised is treated as "personal use only": the farm then does not offer to print the model.
 */
final class License
{
    public static function key(?string $text): string
    {
        $t = mb_strtolower(trim((string) $text));
        if ($t === '') {
            return 'unknown';
        }
        // already one of ours
        if (in_array($t, ['free_personal', 'free_commercial', 'cc0', 'cc_by', 'cc_by_sa', 'cc_by_nd', 'cc_by_nc', 'cc_by_nc_sa', 'cc_by_nc_nd', 'paid', 'on_request', 'unknown'], true)) {
            return $t;
        }
        if (str_contains($t, 'cc0') || str_contains($t, 'public domain') || str_contains($t, 'zero')) {
            return 'cc0';
        }
        $t = str_replace(['creative commons', '—', '–', '-', '_', '(', ')', '4.0', '3.0', 'international'], ' ', $t);
        $has = fn (string ...$words) => (bool) array_filter($words, fn ($w) => preg_match('/\b'.preg_quote($w, '/').'\b/u', $t));
        if ($has('attribution', 'by', 'cc')) {
            $nc = $has('noncommercial', 'nc') || str_contains($t, 'non commercial');
            $nd = $has('noderivatives', 'nd') || str_contains($t, 'no derivatives');
            $sa = $has('sharealike', 'sa') || str_contains($t, 'share alike');

            return 'cc_by'.($nc ? '_nc' : '').($nd ? '_nd' : ($sa ? '_sa' : ''));
        }

        return str_contains($t, 'commercial') && ! str_contains($t, 'non') ? 'free_commercial' : 'free_personal';
    }
}
