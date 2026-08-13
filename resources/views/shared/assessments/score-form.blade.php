@php
    $componentWeightTotal = max(0.01, (float) $components->sum('weight'));
    $activeUserId = auth()->id();
@endphp
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-black text-slate-950">{{ $assignment->student->user->name }}</h2>
                <p class="text-sm text-slate-500">{{ $assignment->student->nim }} - {{ $assignment->place->name }}</p>
            </div>
            <div class="rounded-2xl bg-cyan-50 px-4 py-3 text-sm font-bold text-cyan-800">
                Bobot internal: {{ number_format($componentWeightTotal, 2) }}%
            </div>
        </div>
        @if($assignment->finalScore?->isLocked())
            <div class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">Nilai sudah dikunci/diterbitkan dan tidak dapat diubah.</div>
        @endif
    </section>

    <form method="POST" action="{{ $saveRoute }}" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        @csrf
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Komponen</th>
                        <th class="px-4 py-3">Bobot</th>
                        <th class="px-4 py-3">Nilai</th>
                        <th class="px-4 py-3">Kontribusi</th>
                        <th class="px-4 py-3">Catatan</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($components as $index => $component)
                        @php
                            $score = $assignment->scores
                                ->where('kp_assessment_component_id', $component->id)
                                ->where('assessor_user_id', $activeUserId)
                                ->first();
                            $oldScore = old("scores.$index.score", $score?->score);
                            $oldNote = old("scores.$index.note", $score?->note);
                            $normalizedWeight = ((float) $component->weight / $componentWeightTotal) * 100;
                        @endphp
                        <tr>
                            <td class="px-4 py-4 align-top">
                                <input type="hidden" name="scores[{{ $index }}][component_id]" value="{{ $component->id }}">
                                <p class="font-black text-slate-950">{{ $component->component_name }} @if($component->is_required)<span class="text-rose-600">*</span>@endif</p>
                                @if($component->description)<p class="mt-1 max-w-xl text-xs leading-5 text-slate-500">{{ $component->description }}</p>@endif
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-bold text-slate-700">{{ number_format((float) $component->weight, 2) }}%</p>
                                <p class="text-xs text-slate-500">Normalisasi {{ number_format($normalizedWeight, 2) }}%</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="{{ $component->max_score }}"
                                    name="scores[{{ $index }}][score]"
                                    value="{{ $oldScore }}"
                                    data-weight="{{ $normalizedWeight }}"
                                    class="score-input w-28 rounded-2xl border-slate-200 text-sm font-bold"
                                    @disabled($assignment->finalScore?->isLocked())
                                >
                                <p class="mt-1 text-xs text-slate-500">Maks {{ $component->max_score }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="score-preview rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">0.00</span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <textarea name="scores[{{ $index }}][note]" rows="2" class="w-72 rounded-2xl border-slate-200 text-sm" placeholder="Catatan opsional" @disabled($assignment->finalScore?->isLocked())>{{ $oldNote }}</textarea>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="rounded-full {{ $score?->statusBadgeClass() ?? 'bg-slate-100 text-slate-700' }} px-3 py-1 text-xs font-bold">{{ $score?->statusLabel() ?? 'Belum diisi' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Komponen penilaian belum diatur untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($errors->any())<div class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif
        <div class="mt-6 flex justify-end gap-2"><button class="rounded-2xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700" @disabled($assignment->finalScore?->isLocked())>Simpan Draf</button></div>
    </form>
    <form method="POST" action="{{ $submitRoute }}" onsubmit="return confirm('Kirim nilai? Nilai tidak dapat diubah setelah nilai akhir dikunci.')" class="flex justify-end">@csrf<button class="rounded-2xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white" @disabled($assignment->finalScore?->isLocked())>Kirim Nilai</button></form>
</div>

@push('scripts')
<script>
document.querySelectorAll('.score-input').forEach(function (input) {
    var updatePreview = function () {
        var row = input.closest('tr');
        var target = row ? row.querySelector('.score-preview') : null;
        var value = parseFloat(input.value || '0');
        var weight = parseFloat(input.dataset.weight || '0');
        if (target) {
            target.textContent = ((value * weight) / 100).toFixed(2);
        }
    };
    input.addEventListener('input', updatePreview);
    updatePreview();
});
</script>
@endpush
