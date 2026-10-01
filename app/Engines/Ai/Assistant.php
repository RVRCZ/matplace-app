<?php

namespace App\Engines\Ai;

use App\Engines\Exceptions\EngineException;

/**
 * A language model asked for one structured answer: a category for a model, a text for a post, a draft of an
 * e-mail, themes for collections. Every question says what kind it is (the kind ends up in ai_calls, so /admin/ai
 * shows what each kind of work costs) and what shape the answer must have.
 */
interface Assistant
{
    public function available(): bool;

    /**
     * @param  string  $kind  what the call is for: classify | describe | text | social | email | collections (booked in ai_calls)
     * @param  string  $system  the rules of the task
     * @param  string  $user  the material to work on (treated as text, never as instructions)
     * @param  array<string, mixed>  $schema  JSON schema of the answer (an object)
     * @param  list<string>  $images  pictures to look at: https addresses or paths of files on this machine
     * @param  array{subject_type?: string, subject_id?: int, user_id?: int}  $context  who to book the call on
     * @return array<string, mixed> the answer, in the shape of the schema
     *
     * @throws EngineException
     */
    public function ask(string $kind, string $system, string $user, array $schema, array $images = [], array $context = []): array;
}
