<?php

namespace App\Engines\Translate;

use App\Support\AiUsage;

/**
 * ENGINE_TRANSLATOR=fake: answers "[es] <text>" at once and remembers what it was asked, so tests can check
 * that only the missing languages were requested. Books the call like the real one.
 */
final class FakeTranslator implements Translator
{
    /** @var list<array{to: list<string>, from: ?string, style: string, text: string}> */
    public static array $calls = [];

    /** The language texts without a stated language are "detected" as. */
    public static string $detects = 'en';

    public static function reset(): void
    {
        self::$calls = [];
        self::$detects = 'en';
    }

    public function available(): bool
    {
        return true;
    }

    public function translate(string $text, array $to, ?string $from = null, string $style = self::STYLE_FAITHFUL, array $context = []): Translation
    {
        self::$calls[] = ['to' => array_values($to), 'from' => $from, 'style' => $style, 'text' => $text];
        $source = $from ?? self::$detects;
        $texts = [];
        foreach ($to as $locale) {
            if ($locale !== $source) {
                $texts[$locale] = "[{$locale}] ".$text;
            }
        }
        AiUsage::record($context['kind'] ?? 'translate', 'fake', ['input_tokens' => (int) ceil(mb_strlen($text) / 4), 'output_tokens' => (int) ceil(mb_strlen($text) / 4) * count($texts)], 1, $context);

        return new Translation($source, $texts);
    }
}
