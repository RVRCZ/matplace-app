<?php

namespace App\Domain\Tools;

/**
 * What a visitor puts into a tool, said on its card ("Zadáte: text"): the audit wants every card to say the name, what
 * is needed, a sample and one action (docs/AH.md §3). Read from what the tools are made of, so a new tool is said right
 * without a list to keep: a file for the tools that take a model, a text and/or a picture for the generators that have
 * a text field or take a picture, sizes for the rest; a few tools are said by name.
 */
final class ToolInput
{
    public const KINDS = ['file', 'photo', 'picture', 'text', 'text_or_picture', 'link', 'sizes', 'place'];

    /** Tools whose input is not read from the generators. */
    private const OWN = [
        'figure' => 'photo', 'pet' => 'photo', 'relief' => 'photo', 'filament_art' => 'photo', 'mosaic' => 'photo', 'spare' => 'photo',
        'qr' => 'link', 'map' => 'place', 'svg_to_stl' => 'picture',
        'gifts' => 'text', 'nameplate' => 'text', 'text' => 'text', 'compose' => 'text_or_picture',
    ];

    /** @param  array{intent?: string}  $tool  the tool's entry of config/tools.php */
    public static function of(string $key, array $tool = []): string
    {
        if (isset(self::OWN[$key])) {
            return self::OWN[$key];
        }
        if (($tool['intent'] ?? null) === 'file') {
            return 'file';
        }
        $text = isset(ParametricGenerator::TEXTS[$key]);
        $picture = in_array($key, ParametricGenerator::ARTWORK, true);

        return match (true) {
            $text && $picture => 'text_or_picture',
            $text => 'text',
            $picture => 'picture',
            default => 'sizes',
        };
    }
}
