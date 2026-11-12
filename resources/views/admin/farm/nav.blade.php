@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Farm admin">
    @foreach(['dashboard' => 'admin.farm.dashboard', 'orders' => 'admin.farm.orders', 'printers' => 'admin.farm.printers', 'materials' => 'admin.farm.materials', 'tuning' => 'admin.farm.tuning', 'photobox' => 'admin.farm.photobox', 'videos' => 'admin.youtube.index','settings' => 'admin.farm.settings', 'agents' => 'admin.farm.agents', 'credit' => 'admin.farm.credit'] as $key => $route)
        <a href="{{ route($route) }}" class="chip {{ request()->routeIs($route) || ($key !== 'dashboard' && request()->routeIs($route.'.*')) ? 'chip-on' : '' }}">{{ __('farm.admin.nav.'.$key) }}</a>
    @endforeach
</nav>
{{-- the state of the whole service (App\Domain\Farm\FarmState, docs/AL.md §23) on every page of the farm's admin, loud while
     the farm does not serve customers, so that opening it again is not forgotten --}}
@php
    $farmState = \App\Domain\Farm\FarmState::current();
    $farmHeld = $farmState === 'paused'
        ? \App\Models\FarmOrder::with('user')->where('status', \App\Models\FarmOrder::STATUS_QUEUED)->get()->filter(fn ($o) => \App\Domain\Farm\FarmState::holds($o))->count()
        : 0;
    $farmUntil = \App\Domain\Farm\FarmState::pauseUntil();
@endphp
@if($farmState === 'open')
    <p data-farm-state="open" class="mb-2 text-xs text-slate-600">Stav farmy: <strong class="text-ok">otevřeno</strong> · <a href="{{ route('admin.farm.settings') }}#farm-state" class="underline">změnit</a></p>
@else
    <div data-farm-state="{{ $farmState }}" class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-amber-500 bg-amber-100 px-4 py-3 text-amber-950" role="status">
        <p class="text-sm">
            @if($farmState === 'paused')
                <strong class="text-base">Farma má ODSTÁVKU.</strong>
                Zakázky zákazníků se nespouštějí@if($farmHeld > 0), ve frontě čeká {{ $farmHeld }}@endif.
                @if($farmUntil) Zákazníci vidí: tisk začne nejdřív {{ \App\Domain\Farm\FarmState::dateText($farmUntil, 'cs') }}.@elseif(config('farm.pause_until')) Datum konce odstávky už uplynulo, zákazníci vidí „jakmile farmu znovu spustíme“.@else Zákazníci vidí: tisk začne, jakmile farmu znovu spustíme.@endif
            @else
                <strong class="text-base">Farma je ZAVŘENÁ.</strong> Nové zakázky nejdou založit, ceny tisku a texty o tisku u nás web neukazuje.
            @endif
            Při odstávce nebo zavření zvažte pozastavení reklamy.
        </p>
        <a href="{{ route('admin.farm.settings') }}#farm-state" class="btn-secondary">Změnit stav</a>
    </div>
@endif
@include('partials.flash')
