<?php

namespace App\Domain\Quote;

use App\Models\Calculation;
use App\Models\PrinterProfile;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Turns a calculation (or nothing) into an editable quote and renders its PDF. */
final class QuoteBuilder
{
    /** Lines from the printer's own price breakdown of the calculation; falls back to an empty template. */
    public function fromCalculation(PrinterProfile $profile, ?Calculation $calc): Quote
    {
        $lines = [];
        $params = null;
        $title = null;
        $qty = 1;

        if ($calc) {
            $params = $calc->params;
            $qty = max(1, (int) ($params['quantity'] ?? 1));
            $title = $calc->modelFile?->original_name;
            $bd = collect($calc->prices ?? [])->firstWhere('printer_profile_id', $profile->id)
                ?? collect($calc->rough['prices'] ?? [])->firstWhere('printer_profile_id', $profile->id);
            if (! $bd) {
                // calculation was made without this printer's price list: price it now from grams/minutes
                $g = (float) ($calc->slicer['grams'] ?? $calc->rough['grams'] ?? 0);
                $m = (int) ($calc->slicer['minutes'] ?? $calc->rough['minutes'] ?? 0);
                $dto = $profile->pricingDto((string) ($params['material'] ?? 'PLA'));
                if ($dto && ($g > 0 || $m > 0)) {
                    $bd = app(\App\Domain\Calculation\PriceEngine::class)->price($g, $m, $qty, $dto)->toArray();
                }
            }
            if ($bd) {
                $lines[] = self::line('material', __('quote.line.material', ['material' => $params['material'] ?? '']), $qty, (float) $bd['unit']['material']);
                $lines[] = self::line('time', __('quote.line.time'), $qty, (float) $bd['unit']['time']);
                if ((float) $bd['setup'] > 0) {
                    $lines[] = self::line('setup', __('quote.line.setup'), 1, (float) $bd['setup']);
                }
                if ((float) $bd['unit']['royalty'] > 0) {
                    $lines[] = self::line('royalty', __('quote.line.royalty'), $qty, (float) $bd['unit']['royalty']);
                }
                if ((float) $bd['discount'] > 0) {
                    $lines[] = self::line('discount', __('quote.line.discount', ['pct' => $bd['discount_pct']]), 1, -(float) $bd['discount']);
                }
                if ((float) $bd['margin'] > 0) {
                    $lines[] = self::line('margin', __('quote.line.margin'), 1, (float) $bd['margin']);
                }
                // keep the engine's rounding/min-price as a visible adjustment line
                $sum = Quote::sumLines($lines);
                $diff = round((float) $bd['total'] - $sum, 2);
                if (abs($diff) >= 0.5) {
                    $lines[] = self::line('rounding', __('quote.line.rounding'), 1, $diff);
                }
            }
        }
        if (! $lines) {
            $lines[] = self::line('print', __('quote.line.print'), $qty, 0);
        }

        $pricing = $profile->defaultPricing();

        return Quote::create([
            'token' => Quote::newToken(),
            'number' => Quote::nextNumber($profile->id),
            'printer_profile_id' => $profile->id,
            'calculation_id' => $calc?->id,
            'model_file_id' => $calc?->model_file_id,
            'title' => $title,
            'params' => $params,
            'lines' => $lines,
            'total' => Quote::sumLines($lines),
            'valid_until' => now()->addDays(14),
            'lead_time_days' => $pricing?->lead_time_days ?: $profile->lead_time_days,
            'status' => Quote::STATUS_DRAFT,
        ]);
    }

    public static function line(string $key, string $label, float $qty, float $unitPrice): array
    {
        return ['key' => $key, 'label' => $label, 'qty' => $qty, 'unit_price' => round($unitPrice, 2), 'total' => round($qty * $unitPrice, 2)];
    }

    /** Form input → lines with recomputed totals; keys of existing lines are preserved by position. */
    public function normaliseLines(array $input, ?array $existing = null): array
    {
        $out = [];
        foreach (array_values($input) as $i => $l) {
            $qty = (float) $l['qty'];
            $unit = (float) $l['unit_price'];
            $out[] = [
                'key' => $existing[$i]['key'] ?? 'custom',
                'label' => trim((string) $l['label']),
                'qty' => $qty,
                'unit_price' => round($unit, 2),
                'total' => round($qty * $unit, 2),
            ];
        }

        return $out;
    }

    public function renderPdf(Quote $quote): string
    {
        $pdf = Pdf::loadView('quote.pdf', [
            'quote' => $quote,
            'profile' => $quote->printerProfile,
            'logo' => $this->logoDataUri($quote->printerProfile),
            'preview' => $this->previewDataUri($quote),
        ])->setPaper('a4');
        $rel = 'quotes/'.$quote->printer_profile_id.'/'.$quote->token.'.pdf';
        Storage::disk('local')->put($rel, $pdf->output());
        $quote->pdf_path = $rel;
        $quote->save();

        return $rel;
    }

    public function pdfResponse(Quote $quote): Response
    {
        if (! $quote->pdf_path || ! Storage::disk('local')->exists($quote->pdf_path)) {
            $this->renderPdf($quote);
        }
        $name = ($quote->number ?: 'nabidka').'.pdf';

        return response(Storage::disk('local')->get($quote->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    private function logoDataUri(PrinterProfile $p): ?string
    {
        if (! $p->logo_path || ! Storage::disk('public')->exists($p->logo_path)) {
            return null;
        }
        $bytes = Storage::disk('public')->get($p->logo_path);
        $mime = Storage::disk('public')->mimeType($p->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function previewDataUri(Quote $quote): ?string
    {
        $f = $quote->modelFile;
        if (! $f?->preview_path || ! Storage::disk(\App\Models\ModelFile::DISK)->exists($f->preview_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::disk(\App\Models\ModelFile::DISK)->get($f->preview_path));
    }
}
