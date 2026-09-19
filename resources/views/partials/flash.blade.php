@if(session('status'))<div class="mt-3 rounded-lg bg-teal-50 px-3 py-2 text-sm text-teal-800">{{ session('status') }}</div>@endif
@if(session('error'))<div class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</div>@endif
@if($errors->any())
    <div class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif
