<?php

namespace App\Http\Controllers;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Tools\ArtGenerator;
use App\Domain\Tools\ModelEditor;
use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Api\ConfigController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The tools of session 3: filament art, and the editing of a model file (split, hollow, life size…). */
class EditToolsController extends Controller
{
    /** A picture in the colours of filaments: one print with steps, or a layered picture of plates in a frame. */
    public function filamentArt(Request $request, ArtGenerator $art, MaterialCatalog $materials, ConverterChain $converters): View
    {
        $from = $request->query('from');

        return view('tools.filament_art', [
            'available' => $art->available(),
            'fields' => ArtGenerator::FIELDS, 'choices' => ArtGenerator::CHOICES, 'flags' => ArtGenerator::FLAGS, 'flagsOn' => ArtGenerator::FLAGS_ON, 'folded' => ArtGenerator::FOLDED,
            'sample' => ArtGenerator::SAMPLE,
            'from' => is_string($from) && preg_match('/^[0-9a-f-]{36}$/', $from) ? $from : null,
            'config' => ConfigController::payload($materials, $converters, true),
        ]);
    }

    /** "I have a file" tools: upload a model (or come with ?from=uuid), set the tool, get a new file with the pieces. */
    public function edit(Request $request, string $op, ModelEditor $editor, MaterialCatalog $materials, ConverterChain $converters): View
    {
        abort_unless(in_array($op, ModelEditor::KINDS, true), 404);
        $from = $request->query('from');

        return view('tools.edit', [
            'op' => $op,
            'available' => $editor->available(),
            'fields' => ModelEditor::FIELDS[$op] ?? [], 'choices' => ModelEditor::CHOICES[$op] ?? [], 'flags' => ModelEditor::FLAGS[$op] ?? [], 'flagsOn' => ModelEditor::FLAGS_ON,
            'beds' => ModelEditor::BEDS,
            'from' => is_string($from) && preg_match('/^[0-9a-f-]{36}$/', $from) ? $from : null,
            'config' => ConfigController::payload($materials, $converters, true),
        ]);
    }
}
