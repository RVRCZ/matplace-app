<?php

namespace App\Console\Commands;

use App\Domain\Intake\IntakeHints;
use App\Domain\Intake\IntakeRouter;
use App\Engines\Ai\Assistant;
use App\Models\AiCall;
use App\Models\Intake;
use Illuminate\Console\Command;

/**
 * The golden cases of the home page's router (tests/fixtures/intake/cases.json) through the live assistant: what it
 * answers against what the file expects, with the time and the tokens of every call (docs/AN.md §22 C). The test suite
 * runs the same cases through the keyword guess; this is the check with the real model, run by hand when the key is
 * there (one call per case, a Sonnet, a few crowns a run).
 */
class IntakeCases extends Command
{
    protected $signature = 'matplace:intake-cases {--photo= : a picture attached to the cases that say they have one} {--only= : numbers of the cases to run, comma separated} {--hints : run the examples of the first screen (config/home.php) instead of the golden cases} {--keywords : decide by the keyword guess, no model is called (free)}';

    protected $description = 'Run the golden cases of the home page router through the live assistant';

    public function handle(Assistant $assistant): int
    {
        $keywords = (bool) $this->option('keywords');
        if (! $keywords && ! $assistant->available()) {
            $this->error('The assistant is not configured (ANTHROPIC_API_KEY); the suite tests the keyword guess instead.');

            return self::FAILURE;
        }
        $cases = $this->option('hints') ? self::hintCases() : json_decode((string) file_get_contents(base_path('tests/fixtures/intake/cases.json')), true);
        $only = array_filter(array_map('intval', explode(',', (string) $this->option('only'))));
        $photo = (string) $this->option('photo');
        $rows = [];
        $agree = 0;
        $n = 0;
        foreach ($cases as $i => $case) {
            if ($only && ! in_array($i + 1, $only, true)) {
                continue;
            }
            $locale = (string) ($case['locale'] ?? 'cs');
            $intake = new Intake(['text' => $case['text'], 'locale' => $locale, 'photo_model_path' => null]);
            $images = $case['photo'] && $photo !== '' && is_file($photo) ? [$photo] : [];
            $started = microtime(true);
            $d = null;
            try {
                if ($keywords) {
                    $d = IntakeRouter::guess($case['text'], (bool) $case['photo'], $locale);
                } else {
                    $answer = $assistant->ask(IntakeRouter::KIND, IntakeRouter::system(), IntakeRouter::user($intake).($case['photo'] && ! $images ? "\n(The photo is not attached in this dry run.)" : ''), IntakeRouter::schema(), $images);
                    $d = IntakeRouter::decide($answer, (bool) $case['photo'], $locale);
                }
                $got = $d['route'].($d['tool_key'] ? ':'.$d['tool_key'] : '').($d['wants_shape'] ? ' (shape)' : '');
                $conf = number_format($d['confidence'], 2);
                $sentence = mb_substr($d['summary'], 0, 70);
            } catch (\Throwable $e) {
                $got = 'ERROR '.mb_substr($e->getMessage(), 0, 60);
                $conf = '-';
                $sentence = '';
            }
            $ms = (int) round((microtime(true) - $started) * 1000);
            // a case may accept more than one tool (`tools`: a known painting is a picture for the wall or a coaster)
            $tools = array_values(array_filter((array) ($case['tools'] ?? [$case['tool'] ?? null])));
            $want = $case['route'].($tools ? ':'.implode('|', $tools) : '').(! empty($case['wants_shape']) ? ' (shape)' : '');
            // an example of the first screen that goes to a person may also come back as a shape: both end with the e-mail field
            $ok = isset($d) && $d['route'] === $case['route'] && ($tools ? in_array($d['tool_key'], $tools, true) : $d['tool_key'] === null) && (! empty($case['any_shape']) || $d['wants_shape'] === ! empty($case['wants_shape']))
                // a case that says whether the photo becomes a relief on the thing (params.decor, docs/AR.md §8) is held to it
                && (! array_key_exists('decor', (array) ($case['params'] ?? [])) || ($d['params']['decor'] ?? null) === $case['params']['decor']);
            $agree += $ok ? 1 : 0;
            $n++;
            $call = $keywords ? null : AiCall::where('kind', IntakeRouter::KIND)->latest('id')->first();
            $rows[] = [$case['code'] ?? $i + 1, mb_substr($case['text'] ?: '(photo only)', 0, 40), $want, $got, $ok ? 'ok' : 'DIFF', $conf, $ms, $call ? $call->tokens_in.'/'.$call->tokens_out : '-', $sentence];
        }
        $this->table(['#', 'words', 'expected', 'got', '', 'conf', 'ms', 'tokens in/out', 'sentence (cs)'], $rows);
        $this->info(sprintf('%d of %d as expected', $agree, $n));

        return self::SUCCESS;
    }

    /**
     * The examples under the first screen's field (IntakeHints, docs/AN.md §23.3) as cases: every event, every example,
     * every language the event is offered in, with where config/home.php expects it to go. None has a photo.
     *
     * @return list<array<string, mixed>>
     */
    public static function hintCases(): array
    {
        $out = [];
        foreach (IntakeHints::events() as $key => $event) {
            foreach (IntakeHints::locales($key) as $locale) {
                foreach (IntakeHints::examples($key, $locale, 2027) as $i => $example) {
                    $expect = (string) ($event['examples'][$i] ?? 'human');
                    $out[] = ['code' => $key.'.'.($i + 1).' '.$locale, 'locale' => $locale, 'text' => $example['fill'], 'photo' => false, 'any_shape' => true]
                        + ($expect === 'human' ? ['route' => 'human', 'tool' => null] : ['route' => 'tool', 'tool' => $expect]);
                }
            }
        }

        return $out;
    }
}
