<?php

namespace App\Engines\Ai;

use App\Engines\Exceptions\EngineException;
use App\Support\AiUsage;

/**
 * Tests and local work without an API key. A test says what each kind of question is answered with; without that,
 * the answer is the simplest value of every field of the schema, so the caller's code path is still walked.
 */
final class FakeAssistant implements Assistant
{
    /** @var array<string, array<string, mixed>|\Closure> kind → the answer, or a closure (system, user, images) → answer */
    public static array $answers = [];

    /** @var list<array{kind: string, system: string, user: string, images: list<string>, context: array}> */
    public static array $calls = [];

    /** Set to a text: the next call fails with it. */
    public static ?string $fail = null;

    public static function reset(): void
    {
        self::$answers = [];
        self::$calls = [];
        self::$fail = null;
    }

    public function available(): bool
    {
        return true;
    }

    public function ask(string $kind, string $system, string $user, array $schema, array $images = [], array $context = []): array
    {
        self::$calls[] = compact('kind', 'system', 'user', 'images', 'context');
        if (self::$fail !== null) {
            throw new EngineException(self::$fail);
        }
        AiUsage::record($kind, 'fake', ['input_tokens' => (int) ceil(mb_strlen($system.$user) / 4), 'output_tokens' => 50], 1, $context);
        $answer = self::$answers[$kind] ?? null;
        if ($answer instanceof \Closure) {
            return (array) $answer($system, $user, $images);
        }

        return is_array($answer) ? $answer : self::blank($schema);
    }

    /** The emptiest value that fits a schema. */
    private static function blank(array $schema): mixed
    {
        return match ($schema['type'] ?? 'object') {
            'object' => array_map(fn (array $p) => self::blank($p), (array) ($schema['properties'] ?? [])),
            'array' => [],
            'number', 'integer' => 0,
            'boolean' => false,
            default => (string) (($schema['enum'][0] ?? '') ?: 'fake'),
        };
    }
}
