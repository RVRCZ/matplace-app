{{-- "Nahrát soubor k modelu" (docs/AN.md §15): the file of an inspiration model, uploaded by hand from the source; it becomes a hidden
     ModelFile of the catalogue, is processed and sliced on the queue, and the page then names the price in three materials. --}}
<form method="post" action="{{ route('admin.catalog.file', $model->id) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2 text-sm" data-file-form="{{ $model->id }}">@csrf
    <input type="file" name="file" required accept="{{ implode(',', array_map(fn ($f) => '.'.$f, $formats)) }}" class="max-w-[14rem] text-xs">
    <button class="btn-secondary !min-h-0 py-1.5 text-xs">{{ $model->model_file_id ? 'Nahradit soubor' : 'Nahrát soubor' }}</button>
</form>
