{{-- The filament card from an e-shop link (TODO #71, docs/AL.md §21): one dialog per page, filled by resources/js/site/filament_shop.ts
     from the job's state; the buttons post the admin's corrected card to FilamentShopController::save. --}}
@php
    $kinds = \App\Domain\Farm\FilamentFromShop::kindOptions();
    $texts = [];
    foreach (['url_missing', 'status.queued', 'status.reading', 'status.asking', 'status.done', 'status.failed', 'failed', 'error', 'existing', 'existing_hint', 'field.kind_none', 'field.suggestion',
              'field.photo_none', 'field.settings_none', 'field.nozzle', 'field.nozzle_first', 'field.bed', 'flow', 'field.drying', 'hex_from.declared', 'hex_from.photo', 'hex_from.model', 'saving', 'saved',
              'warn.not_product', 'warn.logo', 'warn.not_spool', 'warn.no_photo', 'warn.no_images', 'warn.rate_limited', 'warn.low_confidence', 'warn.no_settings', 'warn.no_hex', 'warn.kind_unknown',
              'refresh.title', 'refresh.fills', 'refresh.nothing', 'refresh.photo', 'refresh.hex', 'refresh.name_en', 'refresh.settings', 'refresh.saved'] as $k) {
        $texts[$k] = __('farm.admin.shop.'.$k);
    }
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
@endphp
<div data-filament-shop data-start="{{ route('admin.farm.colors.from_url') }}" data-status="{{ route('admin.farm.colors.from_url.status', ['id' => '00000000-0000-0000-0000-000000000000']) }}" data-kinds="{{ json_encode($kinds, JSON_UNESCAPED_UNICODE) }}" data-texts="{{ json_encode($texts, JSON_UNESCAPED_UNICODE) }}">
    <dialog data-shop-dialog class="w-[min(60rem,96vw)] rounded-2xl border border-slate-200 p-0 shadow-xl backdrop:bg-slate-900/40">
        <form method="dialog" class="p-5" data-shop-form>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ __('farm.admin.shop.title') }}</h2>
                    <p class="mt-0.5 break-all text-xs text-slate-500" data-f="url"></p>
                </div>
                <button type="button" data-act="cancel" class="text-sm text-slate-500 underline">{{ __('farm.admin.shop.cancel') }}</button>
            </div>
            <p class="mt-2 text-sm text-slate-600" data-f="lead">{{ __('farm.admin.shop.lead') }}</p>
            <ul class="mt-2 space-y-1 text-sm text-amber-800" data-f="warnings"></ul>

            {{-- the same link again: a spool of a colour we have --}}
            <div class="mt-3 hidden rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm" data-f="existing">
                <p class="font-semibold" data-f="existing_label"></p>
                <p class="mt-1 text-xs text-slate-600">{{ __('farm.admin.shop.existing_hint') }}</p>
            </div>

            {{-- "Načíst znovu" on a colour the catalogue has: only its blanks are filled (docs/AL.md §21.8) --}}
            <div class="mt-3 hidden rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm" data-f="refresh">
                <p class="font-semibold" data-f="refresh_label"></p>
                <p class="mt-1 text-xs text-slate-600" data-f="refresh_fills"></p>
                <label class="mt-1 flex items-start gap-2 text-xs"><input type="checkbox" name="overwrite_settings" value="1" data-f="overwrite_settings" class="mt-0.5 h-4 w-4 shrink-0 accent-action"> {{ __('farm.admin.shop.refresh.overwrite') }}</label>
            </div>

            <div class="mt-3 grid gap-4 sm:grid-cols-[11rem_1fr]" data-f="fields">
                <div>
                    <span class="{{ $lb }}">{{ __('farm.admin.shop.field.photo') }}</span>
                    <div class="mt-1 flex aspect-square items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                        <img data-f="photo" alt="" class="hidden h-full w-full object-contain">
                        <span data-f="photo_none" class="text-xs text-slate-400">{{ __('farm.admin.shop.field.photo_none') }}</span>
                    </div>
                    <label class="mt-1 flex items-start gap-2 text-xs"><input type="checkbox" name="use_photo" value="1" data-f="use_photo" class="mt-0.5 h-4 w-4 shrink-0 accent-action"> {{ __('farm.admin.shop.field.photo_use') }}</label>
                    <div class="mt-2 flex flex-wrap gap-1" data-f="thumbs" title="{{ __('farm.admin.shop.images_hint') }}"></div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="{{ $lb }}">{{ __('farm.admin.shop.field.name_cs') }}<input name="name" data-f="name_cs" required maxlength="80" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">{{ __('farm.admin.shop.field.name_en') }}<input name="name_en" data-f="name_en" maxlength="80" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">{{ __('farm.admin.shop.field.kind') }}
                        <select name="farm_material_id" data-f="material" class="{{ $in }}"></select>
                        <span class="mt-0.5 block font-normal text-amber-700" data-f="suggestion"></span>
                    </label>
                    <label class="{{ $lb }}">{{ __('farm.admin.shop.field.maker') }}<input name="manufacturer" data-f="manufacturer" maxlength="80" list="farm-makers" class="{{ $in }}"><span class="mt-0.5 block font-normal text-slate-500" data-f="colour_name"></span></label>
                    <label class="{{ $lb }}">{{ __('farm.admin.shop.field.hex') }}
                        <span class="mt-1 flex items-center gap-2"><input type="color" data-f="hex_pick" class="h-9 w-12 rounded-lg border border-slate-300"><input name="hex" data-f="hex" maxlength="7" pattern="#[0-9a-fA-F]{6}" class="{{ $in }} mt-0 font-mono"></span>
                        <span class="mt-0.5 block font-normal text-slate-500" data-f="hex_from"></span>
                    </label>
                    <label class="{{ $lb }}">{{ __('farm.admin.shop.field.grams') }} <span class="font-normal text-slate-500" data-f="weight"></span><input type="number" name="remaining_g" data-f="remaining_g" min="0" step="1" class="{{ $in }}"></label>
                    <fieldset class="rounded-xl border border-slate-200 p-2 text-xs sm:col-span-2" data-f="settings_box">
                        <legend class="px-1 font-semibold text-slate-600">{{ __('farm.admin.shop.field.settings') }}</legend>
                        <p data-f="settings_text" class="text-slate-700"></p>
                        <label class="mt-1 flex items-start gap-2"><input type="checkbox" name="save_settings" value="1" data-f="save_settings" class="mt-0.5 h-4 w-4 shrink-0 accent-action"> {{ __('farm.admin.shop.field.settings_save') }}</label>
                    </fieldset>
                    <label class="{{ $lb }} sm:col-span-2">{{ __('farm.admin.shop.field.notes') }} <span class="font-normal text-slate-500" data-f="price"></span><textarea name="test_notes" data-f="test_notes" rows="2" maxlength="2000" class="{{ $in }}"></textarea></label>
                    <p class="text-xs text-slate-500 sm:col-span-2" data-f="agent_notes"></p>
                    <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="enabled" value="1" data-f="enabled" checked class="h-4 w-4 accent-action"> {{ __('farm.admin.shop.field.enabled') }}</label>
                </div>
            </div>

            <p class="mt-3 hidden text-sm text-red-700" data-f="error"></p>
            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <button type="button" data-act="cancel" class="btn-quiet text-sm">{{ __('farm.admin.shop.cancel') }}</button>
                <button type="button" data-act="color" class="btn-quiet text-sm">{{ __('farm.admin.shop.save_color') }}</button>
                <button type="button" data-act="place" class="btn-primary text-sm" data-label-new="{{ __('farm.admin.shop.save_place') }}" data-label-existing="{{ __('farm.admin.shop.place_only') }}">{{ __('farm.admin.shop.save_place') }}</button>
                <button type="button" data-act="refresh" class="btn-primary text-sm hidden">{{ __('farm.admin.shop.refresh.save') }}</button>
            </div>
        </form>
    </dialog>
</div>
