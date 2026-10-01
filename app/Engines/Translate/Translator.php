<?php

namespace App\Engines\Translate;

use App\Engines\Exceptions\EngineException;

/**
 * Descriptions of models in the three languages of the site. One call takes a text and returns it in every
 * language asked for; the language it was written in is detected when it is not known.
 */
interface Translator
{
    /** Keep the author's words: a designer's own description of their model. */
    public const STYLE_FAITHFUL = 'faithful';

    /** A short neutral description for the inspiration catalogue: no coupons, affiliate links or calls to subscribe. */
    public const STYLE_CATALOG = 'catalog';

    public function available(): bool;

    /**
     * @param  list<string>  $to  languages wanted (cs, en, es); the source language is left out of the answer
     * @param  string|null  $from  language of the text when known, null = find out
     * @param  array{kind?: string, subject_type?: string, subject_id?: int, user_id?: int}  $context  who to book the call on (ai_calls)
     *
     * @throws EngineException
     */
    public function translate(string $text, array $to, ?string $from = null, string $style = self::STYLE_FAITHFUL, array $context = []): Translation;
}
