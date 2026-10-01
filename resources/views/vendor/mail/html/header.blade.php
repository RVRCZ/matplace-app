@props(['url'])
{{-- the head of every e-mail: the logo instead of the name in letters (the other parts of the mail layout are Laravel's own) --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('img/logo-email.png') }}" alt="matplace" width="160" height="96" style="width: 160px; height: auto; border: 0;">
</a>
</td>
</tr>
