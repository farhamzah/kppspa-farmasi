@foreach($definition['fields'] as $field)
    @php($name = $field['name'])
    @if($field['type'] === 'table')
        @php($rows = old($name, $payload[$name] ?? [[]]))
        <fieldset class="min-w-0 border-t border-slate-200 pt-4" data-pkm-table>
            <legend class="max-w-full whitespace-normal text-sm font-bold text-slate-800">{{ $field['label'] }}</legend>
            <p class="mb-3 text-xs text-slate-500">Minimal {{ $field['minimum'] }} entri lengkap, atau N/A dengan alasan.</p>
            <div class="space-y-3" data-pkm-rows data-next-index="{{ count($rows) }}">
                @foreach($rows as $rowIndex => $row)
                    @include('student.pkpa-portfolios.puskesmas-table-row')
                @endforeach
            </div>
            <template data-pkm-row-template>
                @include('student.pkpa-portfolios.puskesmas-table-row', ['rowIndex' => '__INDEX__', 'row' => []])
            </template>
            <div class="my-3 flex flex-wrap gap-3">
                <button type="button" data-pkm-add class="rounded-lg border border-cyan-700 px-3 py-2 text-sm font-bold text-cyan-800">Tambah entri</button>
            </div>
            <label class="grid gap-1 text-sm"><span class="font-semibold text-slate-700">Alasan N/A jika kegiatan tidak tersedia</span><textarea name="{{ $name }}_na" rows="2" maxlength="2000" class="w-full rounded-lg border-slate-300 text-sm">{{ old($name.'_na', $payload[$name.'_na'] ?? '') }}</textarea></label>
        </fieldset>
    @elseif($field['type'] === 'puskesmas_ratings')
        <fieldset class="min-w-0 space-y-4">
            <legend class="text-sm font-bold text-slate-800">{{ $field['label'] }}</legend>
            <details class="border-b border-slate-200 pb-3"><summary class="cursor-pointer text-sm font-bold text-cyan-800">Rubrik skor</summary><dl class="mt-3 space-y-3 text-sm">@foreach(array_slice(\App\Support\PkpaPuskesmasPortfolioRevision::reference()['general_rubric'], 1) as $rubric)<div><dt class="font-bold">{{ $rubric[0] }}</dt><dd class="mt-1 text-slate-600">{{ $rubric[1] }}</dd></div>@endforeach</dl></details>
            @foreach(\App\Support\PkpaPuskesmasPortfolioRevision::reference()['self_assessment'] as $criterion)
                @php($rating = old('ratings.'.$criterion['code'], $payload['ratings'][$criterion['code']] ?? []))
                <div class="grid min-w-0 gap-3 border-b border-slate-200 pb-4 sm:grid-cols-[1fr_120px]">
                    <div class="min-w-0"><h4 class="font-bold text-slate-900">{{ $loop->iteration }}. {{ $criterion['title'] }}</h4><p class="mt-1 text-sm text-slate-600">{{ $criterion['indicator'] }}</p></div>
                    <label class="grid content-start gap-1 text-sm"><span>Skor</span><select name="ratings[{{ $criterion['code'] }}][score]" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Pilih</option>@foreach(['1','2','3','4','NA'] as $score)<option value="{{ $score }}" @selected(($rating['score'] ?? '') == $score)>{{ $score === 'NA' ? 'N/A' : $score }}</option>@endforeach</select></label>
                    <label class="grid gap-1 text-sm sm:col-span-2"><span>Bukti/pengalaman</span><textarea name="ratings[{{ $criterion['code'] }}][evidence]" rows="2" maxlength="4000" class="w-full rounded-lg border-slate-300 text-sm">{{ $rating['evidence'] ?? '' }}</textarea></label>
                    <label class="grid gap-1 text-sm sm:col-span-2"><span>Alasan jika N/A</span><textarea name="ratings[{{ $criterion['code'] }}][reason]" rows="2" maxlength="2000" class="w-full rounded-lg border-slate-300 text-sm">{{ $rating['reason'] ?? '' }}</textarea></label>
                </div>
            @endforeach
            <output class="block font-bold text-cyan-800">Nilai tersimpan: {{ data_get($payload, '_result.score', '-') }}/100 ({{ data_get($payload, '_result.assessed', 0) }} aspek dinilai; N/A tidak dihitung).</output>
        </fieldset>
    @else
        <label class="grid min-w-0 gap-2"><span class="text-sm font-bold text-slate-700">{{ $field['label'] }}</span><textarea name="{{ $name }}" rows="{{ $field['rows'] ?? 4 }}" maxlength="30000" class="w-full min-w-0 resize-y rounded-lg border-slate-300 text-sm">{{ old($name, $payload[$name] ?? '') }}</textarea></label>
    @endif
@endforeach

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-pkm-table]').forEach(table => {
            const rows = table.querySelector('[data-pkm-rows]');
            const add = table.querySelector('[data-pkm-add]');
            let nextIndex = Number(rows.dataset.nextIndex);
            add.addEventListener('click', () => {
                if (rows.children.length >= 100) return;
                const fragment = table.querySelector('template').content.cloneNode(true);
                fragment.querySelectorAll('[name]').forEach(input => input.name = input.name.replace('__INDEX__', String(nextIndex)));
                nextIndex++;
                rows.appendChild(fragment);
                add.disabled = rows.children.length >= 100;
                rows.lastElementChild.querySelector('textarea')?.focus();
            });
            rows.addEventListener('click', event => {
                const button = event.target.closest('[data-pkm-remove]');
                if (!button) return;
                button.closest('[data-pkm-row]').remove();
                add.disabled = false;
            });
        });
    });
    </script>
    @endpush
@endonce
