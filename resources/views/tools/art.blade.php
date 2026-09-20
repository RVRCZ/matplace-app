{{-- Small product drawings for the tool cards. One flat style: plastic in the action colour, shadow side darker, light ground. --}}
@php
    $a = '#C94714'; $d = '#A63A10'; $l = '#F2B79F'; $ink = '#172B4D'; $g = '#DDE2EA';
@endphp
<svg viewBox="0 0 120 80" class="h-24 w-full" role="img" aria-label="{{ __('tools.'.$key.'.title') }}" focusable="false">
    <rect width="120" height="80" rx="10" fill="#FCEEE7"/>
    <ellipse cx="60" cy="66" rx="40" ry="5" fill="{{ $g }}"/>
    @switch($key)
        @case('organizer')
            <path d="M22 30 L74 22 L100 34 L48 44Z" fill="{{ $l }}"/><path d="M22 30 L48 44 L48 60 L22 46Z" fill="{{ $a }}"/><path d="M48 44 L100 34 L100 50 L48 60Z" fill="{{ $d }}"/>
            <path d="M39 27.5 L65 39.5 M56.5 25 L82.5 37 M35 37 L87 28" stroke="{{ $d }}" stroke-width="2" fill="none"/>
            @break
        @case('box')
            <path d="M30 34 L66 28 L90 38 L54 46Z" fill="{{ $l }}"/><path d="M30 34 L54 46 L54 64 L30 52Z" fill="{{ $a }}"/><path d="M54 46 L90 38 L90 56 L54 64Z" fill="{{ $d }}"/>
            <path d="M32 18 L68 12 L92 22 L56 30Z" fill="{{ $a }}"/><path d="M32 18 L56 30 L56 34 L32 22Z" fill="{{ $d }}"/><circle cx="72" cy="50" r="3.5" fill="#FCEEE7"/>
            @break
        @case('phone_stand')
            <path d="M34 62 L86 62 L90 66 L30 66Z" fill="{{ $d }}"/><path d="M44 62 L70 16 L76 18 L52 62Z" fill="{{ $a }}"/><path d="M70 62 L62 34 L66 32 L78 62Z" fill="{{ $d }}"/><rect x="33" y="52" width="5" height="10" fill="{{ $a }}"/>
            <rect x="40" y="18" width="24" height="42" rx="3" transform="rotate(30 52 39)" fill="{{ $ink }}" opacity=".85"/>
            @break
        @case('cable_holder')
            <rect x="20" y="40" width="80" height="22" rx="4" fill="{{ $a }}"/>
            @foreach([32, 50, 68, 86] as $x)<circle cx="{{ $x }}" cy="50" r="5.5" fill="#FCEEE7"/><rect x="{{ $x - 3 }}" y="38" width="6" height="12" fill="#FCEEE7"/><path d="M{{ $x }} 50 V18" stroke="{{ $ink }}" stroke-width="4" stroke-linecap="round"/>@endforeach
            @break
        @case('vase')
            <path d="M48 14 H72 C70 30 82 38 80 52 C79 62 70 66 60 66 C50 66 41 62 40 52 C38 38 50 30 48 14Z" fill="{{ $a }}"/><path d="M60 14 H72 C70 30 82 38 80 52 C79 62 70 66 60 66Z" fill="{{ $d }}"/><ellipse cx="60" cy="14" rx="12" ry="3" fill="{{ $l }}"/>
            @break
        @case('figure')
            <circle cx="60" cy="26" r="11" fill="{{ $a }}"/><path d="M40 60 C40 44 50 38 60 38 C70 38 80 44 80 60Z" fill="{{ $a }}"/><path d="M60 15 A11 11 0 0 1 60 37 V38 C70 38 80 44 80 60 H60Z" fill="{{ $d }}"/><rect x="34" y="60" width="52" height="6" rx="2" fill="{{ $ink }}"/>
            @break
        @case('relief')
            <rect x="30" y="14" width="60" height="50" rx="3" fill="{{ $a }}"/><rect x="35" y="19" width="50" height="40" fill="#FFF3D6"/><circle cx="72" cy="30" r="5" fill="{{ $l }}"/><path d="M35 59 L52 38 L62 50 L70 42 L85 59Z" fill="{{ $d }}" opacity=".75"/>
            @break
        @case('sign')
            <rect x="22" y="26" width="76" height="30" rx="8" fill="{{ $a }}"/><circle cx="31" cy="41" r="3" fill="#FCEEE7"/><path d="M42 47 V35 H48 M52 47 V35 L58 47 V35 M64 35 H72 M68 35 V47 M78 47 V35 H84 M78 41 H83" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('qr')
            <rect x="34" y="12" width="52" height="52" rx="4" fill="{{ $a }}"/><g fill="#fff"><rect x="40" y="18" width="12" height="12"/><rect x="68" y="18" width="12" height="12"/><rect x="40" y="46" width="12" height="12"/><rect x="57" y="22" width="5" height="5"/><rect x="57" y="35" width="5" height="5"/><rect x="66" y="40" width="5" height="5"/><rect x="74" y="48" width="6" height="6"/><rect x="62" y="50" width="5" height="5"/><rect x="44" y="35" width="5" height="5"/></g><g fill="{{ $a }}"><rect x="43" y="21" width="6" height="6"/><rect x="71" y="21" width="6" height="6"/><rect x="43" y="49" width="6" height="6"/></g>
            @break
        @case('logo')
            <rect x="26" y="20" width="68" height="42" rx="5" fill="{{ $d }}"/><path d="M60 26 L68 42 L86 44 L72 54 L76 60 L60 52 L44 60 L48 54 L34 44 L52 42Z" fill="{{ $l }}"/>
            @break
        @case('stamp')
            <rect x="52" y="12" width="16" height="26" rx="6" fill="{{ $d }}"/><rect x="34" y="36" width="52" height="16" rx="4" fill="{{ $a }}"/><rect x="38" y="52" width="44" height="6" fill="{{ $ink }}"/><path d="M44 66 H76" stroke="{{ $a }}" stroke-width="3" stroke-dasharray="5 4"/>
            @break
        @case('stencil')
            <rect x="24" y="18" width="72" height="46" rx="4" fill="{{ $a }}"/><path d="M40 52 L48 28 L56 52 M43 44 H53 M64 28 V52 H76" stroke="#FCEEE7" stroke-width="5" fill="none" stroke-linecap="butt"/>
            @break
        @case('lightbox')
            <rect x="24" y="22" width="72" height="38" rx="5" fill="{{ $ink }}"/><rect x="28" y="26" width="64" height="30" rx="3" fill="#FFF3D6"/><path d="M38 48 V34 L46 48 V34 M54 34 V48 M62 34 H70 M66 34 V48 M76 34 V48 H84" stroke="{{ $a }}" stroke-width="3.5" fill="none" stroke-linecap="round"/>
            @break
        @case('mosaic')
            @foreach([0,1,2,3,4] as $r)@foreach([0,1,2,3,4,5,6] as $c)<rect x="{{ 25 + $c * 10 }}" y="{{ 14 + $r * 10 }}" width="9" height="9" rx="1.5" fill="{{ [$a, $d, $l, $ink, '#FFF3D6'][($r * 3 + $c * 2 + ($r % 2)) % 5] }}"/>@endforeach @endforeach
            @break
        @case('calc')
            <path d="M40 14 H70 L84 28 V66 H40Z" fill="#fff" stroke="{{ $g }}" stroke-width="2"/><path d="M70 14 V28 H84" fill="{{ $g }}"/><path d="M50 50 L62 44 L74 50 L62 56Z" fill="{{ $l }}"/><path d="M50 50 L62 56 V66 L50 60Z" fill="{{ $a }}"/><path d="M62 56 L74 50 V60 L62 66Z" fill="{{ $d }}"/><text x="47" y="38" font-size="9" font-weight="700" fill="{{ $ink }}" font-family="sans-serif">STL</text>
            @break
        @case('check')
            <path d="M38 40 L60 28 L82 40 L60 52Z" fill="{{ $l }}"/><path d="M38 40 L60 52 V68 L38 56Z" fill="{{ $a }}"/><path d="M60 52 L82 40 V56 L60 68Z" fill="{{ $d }}"/><circle cx="84" cy="24" r="13" fill="#18794E"/><path d="M77 24 L82 29 L91 19" stroke="#fff" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('personalize')
            <path d="M34 40 L60 28 L86 40 L60 52Z" fill="{{ $l }}"/><path d="M34 40 L60 52 V68 L34 56Z" fill="{{ $a }}"/><path d="M60 52 L86 40 V56 L60 68Z" fill="{{ $d }}"/><text x="63" y="59" font-size="9" font-weight="700" fill="#fff" font-family="sans-serif" transform="skewY(-25) translate(0 28)">Eva</text>
            @break
        @case('spare')
            <circle cx="52" cy="42" r="18" fill="{{ $a }}"/><circle cx="52" cy="42" r="7" fill="#FCEEE7"/>@foreach([0,45,90,135,180,225,270,315] as $deg)<rect x="48" y="18" width="8" height="9" rx="2" fill="{{ $a }}" transform="rotate({{ $deg }} 52 42)"/>@endforeach
            <path d="M74 60 L96 38" stroke="{{ $ink }}" stroke-width="5" stroke-linecap="round"/><path d="M90 30 a8 8 0 1 0 10 10 l-6 -1 l-3 -3Z" fill="{{ $ink }}"/>
            @break
        @case('printer_tools')
            <rect x="30" y="18" width="60" height="46" rx="4" fill="{{ $ink }}"/><rect x="36" y="24" width="48" height="30" fill="#FCEEE7"/><rect x="54" y="28" width="12" height="8" fill="{{ $d }}"/><path d="M58 36 L60 42 L62 36Z" fill="{{ $d }}"/><rect x="50" y="44" width="20" height="8" fill="{{ $a }}"/>
            @break
    @endswitch
</svg>
