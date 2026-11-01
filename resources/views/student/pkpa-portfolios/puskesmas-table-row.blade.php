<div data-pkm-row class="grid min-w-0 gap-3 border-l-2 border-slate-200 pl-3 sm:grid-cols-2">
    @foreach($field['columns'] as $column => $label)
        <label class="grid min-w-0 gap-1 text-sm"><span class="font-semibold text-slate-700">{{ $label }}</span><textarea name="{{ $name }}[{{ $rowIndex }}][{{ $column }}]" rows="2" maxlength="10000" class="w-full min-w-0 rounded-lg border-slate-300 text-sm">{{ $row[$column] ?? '' }}</textarea></label>
    @endforeach
    <div class="sm:col-span-2"><button type="button" data-pkm-remove class="px-1 py-2 text-sm font-semibold text-rose-700">Hapus entri</button></div>
</div>
