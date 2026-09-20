<?php

namespace App\Engines\Contracts;

use App\Engines\DTO\SliceParams;

/**
 * "I have a printer": a ready-to-open slicer project (3MF) for a chosen printer, with the model placed and
 * the print settings filled in. No G-code: the owner's slicer produces it for their real machine.
 */
interface ProjectExporter
{
    public function name(): string;

    /**
     * @return array<int, array{id:string, vendor:string, vendor_label:string, model:string, bed:array{x:int,y:int,z:int}, materials:string[]}>
     */
    public function printers(): array;

    /**
     * @param  array<string, mixed>  $hints  tool-specific wishes (kind: lithophane | generated | …)
     * @return string absolute path of the 3MF (caller sends and may delete it)
     */
    public function export(string $stlPath, string $printerId, SliceParams $params, array $hints = []): string;
}
