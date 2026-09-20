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
    /**
     * A calculation (or nothing) → draft quote. The printer's price list fills the internal cost sheet, so the suggested
     * price equals what the calculator showed; the printer then adds hand work, a reserve for failed prints, shipping…
     */
    public function fromCalculation(PrinterProfile $profile, ?Calculation $calc): Quote
    {
        $params = $calc?->params;
        $qty = max(1, (int) ($params['quantity'] ?? 1));
        $pricing = $profile->defaultPricing();
        $dto = $profile->pricingDto((string) ($params['material'] ?? 'PLA'));
        $grams = (float) ($calc?->slicer['grams'] ?? $calc?->rough['grams'] ?? 0);
        $minutes = (float) ($calc?->slicer['minutes'] ?? $calc?->rough['minutes'] ?? 0);

        $cost = [
            'quantity' => $qty,
            'material_cost' => round($grams * $qty * (float) ($dto?->pricePerGram ?? 0), 2),
            'machine_hours' => round($minutes * (float) ($dto?->timeFactor ?? 1) * $qty / 60, 2),
            'machine_rate' => (float) ($dto?->hourlyRate ?? 0),
            'setup_cost' => (float) ($dto?->setupFee ?? 0),
            'labour_minutes' => 0, 'labour_rate' => 0, 'failure_pct' => 0,
            'mode' => CostSheet::MODE_MARKUP,                       // the price list's "margin_pct" has always been a markup on cost
            'pct' => (float) ($dto?->marginPct ?? 0),
            'min_price' => (float) ($dto?->minPrice ?? 0),
        ];
        $extras = [];
        $discountPct = $dto ? $dto->discountPctFor($qty) : 0.0;

        $quote = new Quote([
            'token' => Quote::newToken(),
            'number' => Quote::nextNumber($profile->id),
            'printer_profile_id' => $profile->id,
            'calculation_id' => $calc?->id,
            'model_file_id' => $calc?->model_file_id,
            'title' => $calc?->modelFile?->original_name,
            'params' => $params ?? ['quantity' => 1],
            'valid_until' => now()->addDays(14),
            'lead_time_days' => $pricing?->lead_time_days ?: $profile->lead_time_days,
            'status' => Quote::STATUS_DRAFT,
            'version' => 1,
        ]);
        $sheet = CostSheet::compute($cost, (int) config('pricing.round_to', 1));
        if ($discountPct > 0) {
            // quantity discounts are something the customer should see
            $extras[] = self::line('custom', __('quote.line.discount', ['pct' => $discountPct]), 1, -round($sheet['final'] * $discountPct / 100, 0));
        }
        $this->apply($quote, $cost, $extras);
        $quote->save();

        return $quote;
    }

    /** Cost sheet + customer-visible extra items + shipping → lines and total. The one place a quote's money is put together. */
    public function apply(Quote $quote, array $costInput, array $extraLines): void
    {
        $sheet = CostSheet::compute($costInput, (int) config('pricing.round_to', 1));
        $lines = [self::line('production', __('quote.line.production'), $sheet['quantity'], $sheet['unit_price'])];
        $lines[0]['total'] = $sheet['final'];                       // unit price is rounded for display, the total is exact
        foreach ($extraLines as $l) {
            $lines[] = ['key' => 'custom'] + $l;
        }
        $quote->cost = $sheet;
        $quote->lines = $lines;
        $quote->params = ['quantity' => $sheet['quantity']] + (array) $quote->params;
        $quote->total = Quote::sumLines($lines) + round((float) $quote->shipping_price, 0);
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
                'key' => 'custom',
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
