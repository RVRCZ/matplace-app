<?php

namespace App\Engines\Repair;

use App\Engines\Exceptions\EngineException;

/** Runs one of the scripts in engines/python and hands back the JSON object it prints (PythonTool; a stand-in in tests). */
interface ScriptRunner
{
    /**
     * @return array<string,mixed>
     *
     * @throws EngineException when the script prints no JSON
     */
    public function runScript(string $script, array $args, int $timeout = 90): array;
}
