<?php

namespace App\Support;

/**
 * The texts the calculator's scripts say (resources/js/calc/*.ts read them from window.MP_I18N), in the wording of the
 * step that follows (NextStep). Served with the calculator's constants as one JSON the browser keeps for a day
 * (ConfigController::calc, resources/js/app.ts, docs/AN.md §12.4) instead of 24 kB inline in every page; the key list
 * used to live in calculator/index.blade.php.
 */
final class CalculatorTexts
{
    public const KEYS = [
        'calc.status.reading', 'calc.status.rough', 'calc.status.uploading', 'calc.status.queued', 'calc.status.done',
        'calc.status.failed', 'calc.status.not_model', 'calc.status.converting', 'calc.status.big_model', 'calc.status.big_only', 'calc.status.big_only_n', 'calc.status.by_parts', 'calc.colors.count', 'calc.colors.merged', 'calc.colors.price_one', 'calc.colors.price_multi', 'calc.colors.price_same', 'calc.colors.order', 'calc.price.orient_range', 'calc.price.shape_hint', 'calc.time.range', 'calc.price.from', 'calc.price.range', 'calc.price.per_piece',
        'calc.days', 'calc.cta.copied', 'calc.error.read', 'calc.error.upload', 'calc.error.too_big', 'calc.est_only',
        'calc.profile.budget', 'calc.profile.standard', 'calc.profile.express', 'calc.profile.mine', 'calc.others_from',
        'calc.breakdown.material', 'calc.breakdown.time', 'calc.breakdown.setup', 'calc.breakdown.total', 'calc.breakdown.discount', 'calc.breakdown.margin', 'calc.breakdown.min_price', 'calc.breakdown.rounded',
        'calc.warn.exceeds_typical_bed', 'calc.warn.supports_added', 'calc.warn.not_watertight',
        'calc.warn.multiple_shells', 'calc.warn.flipped_normals', 'calc.printers_count',
        'search.searching', 'search.identifying', 'search.none', 'search.error', 'search.not_image', 'search.daily_limit',
        'models.search.open_print', 'models.search.open_inspiration', 'models.search.label_print', 'models.search.label_inspiration', 'search.open_source',
        'search.size_guess', 'search.price_range', 'search.range_hint', 'search.have_file', 'search.generate', 'search.designer_soon', 'hero.soon', 'calc.size', 'calc.material', 'inquiry.error',
        'search.gen_size', 'search.generating', 'search.gen_done', 'search.gen_failed', 'search.gen_daily_limit', 'search.gen_global_limit', 'search.gen_text_hint',
        'refine.working', 'refine.failed', 'pedestal.working', 'pedestal.failed', 'mold.working', 'mold.failed', 'mold.unavailable', 'mold.report', 'mold.report.undercuts', 'mold.report.large', 'mold.report.parts', 'mold.report.filled', 'mold.report.silicone', 'mold.report.silicone.how', 'mold.report.angle', 'mold.verdict.rigid', 'mold.verdict.flexible', 'mold.verdict.silicone', 'calc.tip.mold', 'calc.tip.repaired', 'refine.photo_only', 'advice.title', 'advice.lead', 'advice.button', 'advice.working', 'advice.failed', 'advice.daily_limit', 'advice.unavailable', 'advice.level.important', 'advice.level.tip', 'advice.level.fine', 'advice.disclaimer', 'check.head.error', 'check.head.advice', 'check.head.ok', 'check.group.error', 'check.group.advice', 'check.group.ok', 'check.disclaimer', 'check.units_tiny', 'check.units_tiny.impact', 'check.units_huge', 'check.units_huge.impact', 'check.very_small', 'check.very_small.impact', 'check.exceeds_bed', 'check.exceeds_bed.impact', 'check.parts_fit', 'check.part_exceeds_bed', 'check.part_exceeds_bed.impact', 'check.size_ok', 'check.too_thin', 'check.too_thin.impact', 'check.watertight_ok', 'check.not_watertight', 'check.not_watertight.impact', 'check.flipped_normals', 'check.flipped_normals.impact', 'check.multiple_shells', 'check.multiple_shells.impact', 'check.heavy_mesh', 'check.heavy_mesh.impact', 'check.very_coarse', 'check.very_coarse.impact',
        'calc.tip.organizer', 'calc.tip.modular', 'farm.calc_parts_by_colour', 'farm.calc_nearest', 'farm.calc_nearest_out', 'param.part.tray', 'param.part.bin', 'param.part.body.logo', 'param.part.stand.logo', 'param.part.body.vase', 'param.part.body.stamp', 'param.part.body.qr', 'param.part.body.lightbox', 'calc.tip.stencil', 'calc.tip.lightbox', 'param.part.face', 'param.part.diffuser', 'param.part.back', 'calc.tip.vase', 'calc.tip.logo', 'calc.tip.stamp', 'calc.tip.qr', 'calc.edit_design', 'param.part.saucer', 'param.part.handle', 'param.part.stand', 'calc.tip.box', 'calc.tip.phone_stand', 'calc.tip.cable_holder', 'download.parts', 'param.part.body', 'param.part.lid', 'calc.tip.generated', 'calc.tip.lithophane', 'calc.tip.relief', 'calc.tip.sign',
        'calc.fit.bed', 'calc.fit.none', 'calc.fit.plates', 'calc.size.limit', 'calc.status.stale', 'calc.recalc', 'calc.price.orient', 'compare.col.material', 'compare.col.price', 'compare.col.time', 'compare.col.weight', 'compare.col.heat', 'compare.col.strength', 'compare.col.outdoor', 'compare.col.food', 'compare.outdoor.yes', 'compare.outdoor.limited', 'compare.outdoor.no', 'compare.food.liner', 'compare.food.no', 'compare.flexible', 'compare.pick', 'compare.chosen', 'calc.mode.normal', 'calc.mode.silent', 'calc.mode.sport', 'calc.facts.rough', 'calc.facts.material', 'calc.facts.layers', 'calc.facts.supports', 'calc.facts.supports_yes', 'calc.facts.supports_no', 'calc.facts.infill', 'calc.facts.length', 'calc.status.done_facts',
    ];

    /** How long the browser may keep the JSON: a day; the version in its address changes with the content, so nothing stale is ever read. */
    public const MAX_AGE = 86400;

    /** @return array<string, string> key → the text in the page's language and the wording of the step that follows */
    public static function all(int $maxUploadMb): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $out[$key] = NextStep::text($key, ['max' => $maxUploadMb, 'n' => ':n']);
        }

        return $out;
    }

    /**
     * What the page's scripts read as window.MP_CONFIG and window.MP_I18N.
     *
     * @param  array<string, mixed>  $config  ConfigController::payload()
     * @return array{config: array<string, mixed>, i18n: array<string, string>}
     */
    public static function payload(array $config): array
    {
        return ['config' => $config, 'i18n' => self::all((int) ($config['max_upload_mb'] ?? 120))];
    }

    /**
     * The address of this page's JSON: the language, the next step the page promises and a hash of the content, so the
     * browser's copy of a day is never stale and two visitors with different delivery hints never share one.
     *
     * @param  array<string, mixed>  $config
     */
    public static function url(array $config): string
    {
        $version = substr(sha1((string) json_encode(self::payload($config))), 0, 12);

        return route('api.calc_config', ['l' => app()->getLocale(), 'm' => NextStep::mode(), 'v' => $version]);
    }
}
