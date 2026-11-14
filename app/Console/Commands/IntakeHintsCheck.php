<?php

namespace App\Console\Commands;

use App\Domain\Intake\IntakeHintCheck;
use Illuminate\Console\Command;

/**
 * Every example under the first screen's field against the rule "a hint ends on a design that matches its sentence"
 * (docs/AN.md §25): where the wish goes, what the tool's page opens with, and what of the sentence got lost on the
 * way. The texts are the ones the page shows, so the admin's own (/admin/intake/hints) are checked on the machine
 * that holds them: run it on the server after a hint was changed there. Free: the keyword router decides, no model
 * is asked. `--json` prints the list for the walk in a browser (scripts/hints_walk.mjs).
 */
class IntakeHintsCheck extends Command
{
    protected $signature = 'matplace:intake-hints {--json : the whole list as JSON, for scripts/hints_walk.mjs} {--lang= : languages, comma separated (default: all)} {--only= : codes like steady.3, comma separated}';

    protected $description = 'Check that every example of the home page ends on a design that matches its sentence';

    public function handle(): int
    {
        $locales = array_values(array_filter(explode(',', (string) $this->option('lang')))) ?: null;
        $only = array_values(array_filter(explode(',', (string) $this->option('only'))));
        $hints = array_values(array_filter(IntakeHintCheck::all($locales), fn (array $h) => ! $only || in_array($h['code'], $only, true)));
        if ($this->option('json')) {
            $this->line((string) json_encode($hints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        $bad = 0;
        $rows = [];
        foreach ($hints as $h) {
            $bad += $h['lost'] ? 1 : 0;
            $opens = $h['tool'] === null ? 'a person' : $h['tool'].($h['ask'] ? ' (asks for the '.$h['ask'].')' : '');
            $rows[] = [$h['code'].' '.$h['locale'], mb_substr($h['fill'], 0, 58), $opens, mb_substr(implode(' · ', $h['understood']), 0, 70), $h['lost'] ? 'NO: '.implode('; ', array_map(fn (string $l) => IntakeHintCheck::say($l), $h['lost'])) : 'ok'];
        }
        $this->table(['hint', 'sentence', 'opens', 'the page opens with', 'matches'], $rows);
        $this->info(sprintf('%d examples, %d match their sentence, %d do not', count($hints), count($hints) - $bad, $bad));

        return $bad ? self::FAILURE : self::SUCCESS;
    }
}
