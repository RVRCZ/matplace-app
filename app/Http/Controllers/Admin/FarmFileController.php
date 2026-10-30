<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\PrinterFiles;
use App\Http\Controllers\Controller;
use App\Models\FarmCommand;
use App\Models\FarmPrinter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The files on a printer's disk, from the admin's printer page (docs/AM.md §10): ask the agent for the list, bring one
 * file up to the server, delete one, tidy the disk of the farm's own files. Every action is a command the agent carries
 * out; the page shows the last list and what the commands said. The file of a running job is never deleted.
 */
class FarmFileController extends Controller
{
    public function __construct(private readonly PrinterFiles $files) {}

    public function list(Request $request, FarmPrinter $printer): RedirectResponse
    {
        if (! $printer->isAgentDriven()) {
            return $this->back($printer)->with('error', __('farm.admin.files.manual_printer'));
        }
        $this->command($request, $printer, FarmCommand::TYPE_FILES);

        return $this->back($printer)->with('status', __('farm.admin.files.queued', ['what' => __('farm.admin.command_type.files')]));
    }

    public function fetch(Request $request, FarmPrinter $printer): RedirectResponse
    {
        $name = $this->listed($request, $printer);
        if ($name === null) {
            return $this->back($printer)->with('error', __('farm.admin.files.not_listed', ['name' => (string) $request->input('name')]));
        }
        $this->command($request, $printer, FarmCommand::TYPE_FETCH, ['name' => $name]);

        return $this->back($printer)->with('status', __('farm.admin.files.queued', ['what' => __('farm.admin.command_type.fetch').' '.$name]));
    }

    public function delete(Request $request, FarmPrinter $printer): RedirectResponse
    {
        $name = $this->listed($request, $printer);
        if ($name === null) {
            return $this->back($printer)->with('error', __('farm.admin.files.not_listed', ['name' => (string) $request->input('name')]));
        }
        // the file of the job that prints or waits stays, whoever asks (the agent refuses it once more on its side)
        if (in_array($name, $this->files->keep($printer), true)) {
            return $this->back($printer)->with('error', __('farm.admin.files.in_use', ['name' => $name]));
        }
        $this->command($request, $printer, FarmCommand::TYPE_DELETE, ['name' => $name]);

        return $this->back($printer)->with('status', __('farm.admin.files.queued', ['what' => __('farm.admin.command_type.delete').' '.$name]));
    }

    /** The farm's own files that no job needs: the agent deletes them and reports what went and how much came free. */
    public function cleanup(Request $request, FarmPrinter $printer): RedirectResponse
    {
        if (! $printer->isAgentDriven()) {
            return $this->back($printer)->with('error', __('farm.admin.files.manual_printer'));
        }
        $keep = $this->files->keep($printer);
        $ours = collect($this->files->listing($printer)['files'])->filter(fn ($f) => PrinterFiles::isOurs($f['name']) && ! in_array($f['name'], $keep, true));
        if ($ours->isEmpty()) {
            return $this->back($printer)->with('error', __('farm.admin.files.cleanup_none'));
        }
        $this->command($request, $printer, FarmCommand::TYPE_CLEANUP, ['keep' => $keep]);

        return $this->back($printer)->with('status', __('farm.admin.files.queued', ['what' => __('farm.admin.command_type.cleanup').' ('.$ours->count().')']));
    }

    /** A fetched copy, down to the admin's computer. */
    public function download(FarmPrinter $printer, string $name): BinaryFileResponse
    {
        $path = $this->files->fetchedPath($printer, $name);
        abort_unless($path !== null, 404);

        return response()->download($path, $name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store']);
    }

    /** The server's copy is not needed any more (the printer's own file is untouched). */
    public function forget(Request $request, FarmPrinter $printer): RedirectResponse
    {
        $name = PrinterFiles::plain($request->input('name'));
        $gone = $name !== null && $this->files->forget($printer, $name);

        return $this->back($printer)->with($gone ? 'status' : 'error', $gone ? __('farm.admin.deleted', ['what' => $name]) : __('farm.admin.files.not_listed', ['name' => (string) $request->input('name')]));
    }

    /** The plain name the request asks about, when the printer's last list has it. */
    private function listed(Request $request, FarmPrinter $printer): ?string
    {
        $name = PrinterFiles::plain($request->input('name'));
        if ($name === null || ! $printer->isAgentDriven()) {
            return null;
        }

        return collect($this->files->listing($printer)['files'])->contains(fn ($f) => $f['name'] === $name) ? $name : null;
    }

    private function command(Request $request, FarmPrinter $printer, string $type, array $payload = []): FarmCommand
    {
        return FarmCommand::create(['farm_printer_id' => $printer->id, 'type' => $type, 'payload' => $payload ?: null, 'created_by' => $request->user()->id]);
    }

    private function back(FarmPrinter $printer): RedirectResponse
    {
        return redirect()->to(route('admin.farm.printers.edit', $printer).'#files');
    }
}
