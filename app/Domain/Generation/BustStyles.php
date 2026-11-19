<?php

namespace App\Domain\Generation;

use App\Domain\Farm\FarmSettings;

/**
 * Which finishes of a bust the bust page offers and which one it starts with: the owner's choice at /admin/ai
 * ("Typy busty"), kept in farm_settings under `ai.bust_styles` as {shown: [...], default: "..."}.
 *
 * Until he chooses, every style is shown and the page starts with the turned foot, as it did before the three
 * classic styles came (Roman, 17 Nov 2026: he will try them with ideal photos first, then hide some and pick one).
 * A hidden style is not offered and a new request for it is refused; a model that was made with it still opens and
 * its base can be changed away from it (or its name changed), it only cannot be chosen anew.
 */
final class BustStyles
{
    public const KEY = 'ai.bust_styles';

    /** What the page starts with while the owner has not chosen. */
    public const FALLBACK = 'classic';      // the bust on a block (Roman, 19 Nov 2026); it was the modern 'socle'

    public function __construct(private readonly FarmSettings $settings) {}

    /** @return string[] the styles on offer, in the order of the page */
    public function shown(): array
    {
        $stored = $this->stored();
        if (! is_array($stored['shown'] ?? null)) {
            return PedestalChanger::BUST_STYLES;
        }

        return array_values(array_intersect(PedestalChanger::BUST_STYLES, $stored['shown']));
    }

    /** @return string[] */
    public function hidden(): array
    {
        return array_values(array_diff(PedestalChanger::BUST_STYLES, $this->shown()));
    }

    /** The style a bust starts with: the owner's choice while it is shown, else the first shown one, else the plain round base. */
    public function default(): string
    {
        $shown = $this->shown();
        $wish = (string) ($this->stored()['default'] ?? self::FALLBACK);

        return in_array($wish, $shown, true) ? $wish : ($shown[0] ?? 'round');
    }

    /**
     * Every base a new request or a change of base may ask for: the plain bases and the shown styles, plus `$own`,
     * the style the model at hand already has (a hidden one stays with its model).
     *
     * @return string[]
     */
    public function allowed(?string $own = null): array
    {
        $hidden = array_diff($this->hidden(), [$own]);

        return array_values(array_diff(PedestalChanger::TYPES, $hidden));
    }

    /** @param  string[]  $shown */
    public function save(array $shown, ?string $default): void
    {
        $shown = array_values(array_intersect(PedestalChanger::BUST_STYLES, $shown));
        $this->settings->set(self::KEY, ['shown' => $shown, 'default' => in_array($default, $shown, true) ? $default : ($shown[0] ?? null)]);
    }

    private function stored(): array
    {
        $value = $this->settings->get(self::KEY);

        return is_array($value) ? $value : [];
    }
}
