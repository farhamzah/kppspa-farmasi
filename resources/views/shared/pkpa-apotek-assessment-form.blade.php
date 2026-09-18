@php
    $assessorType = $assignment->assessor_type;
    $sections = \App\Support\PkpaApotekAssessment::sections($assessorType);
    $levelLabels = \App\Support\PkpaApotekAssessment::levelLabels();
    $summary = $score->source_summary ?? [];
    $savedCriteria = data_get($summary, 'criteria', []);
    $feedback = data_get($summary, 'feedback', []);
    $recommendations = data_get($summary, 'recommendations', []);
    $attendance = $attendanceSummary ?? data_get($summary, 'attendance', []);
    $locked = in_array($score->status, ['submitted', 'approved', 'locked'], true);
    $formId = 'assessment-form-'.$score->id;
    $isSubmittedForm = (string) old('assessment_score_id') === (string) $score->id;
@endphp

<details class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white" @if($isSubmittedForm) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-slate-50 px-4 py-4 marker:hidden sm:px-5">
        <span>
            <span class="block text-sm font-black text-slate-950">{{ $locked ? 'Lihat Penilaian Apotek' : 'Isi Penilaian Apotek' }}</span>
            <span class="mt-1 block text-xs text-slate-500">{{ $locked ? 'Nilai sudah dikirim dan terkunci.' : 'Rubrik resmi, nilai otomatis, dan umpan balik.' }}</span>
        </span>
        <span class="rounded-xl {{ $locked ? 'bg-emerald-50 text-emerald-700' : 'bg-cyan-700 text-white' }} px-4 py-2 text-xs font-black">{{ $locked ? 'Terkirim' : (($score->status === 'draft') ? 'Lanjutkan Draf' : 'Mulai') }}</span>
    </summary>
<form id="{{ $formId }}" method="POST" action="{{ route($routePrefix.'.pkpa-assessments.scores.save', $score) }}" class="border-t border-slate-200" data-assessment-form>
    @csrf
    <input type="hidden" name="assessment_score_id" value="{{ $score->id }}">
    <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-black text-slate-950">Rubrik Penilaian PKPA Apotek</p>
                <p class="mt-1 text-sm text-slate-600">Pilih skor 1–5 pada setiap butir. Buka panduan skor bila memerlukan rincian.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold uppercase text-slate-500">Nilai sementara</span>
                <output class="min-w-20 rounded-xl bg-cyan-700 px-4 py-2 text-center text-xl font-black text-white" data-assessment-total>{{ number_format((float) ($score->raw_score ?? 0), 2) }}</output>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold text-slate-600">
            @foreach($levelLabels as $value => $label)
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">{{ $value }} · {{ $label }}</span>
            @endforeach
        </div>
    </div>

    @if($assessorType === 'field_supervisor')
        <div class="border-b border-cyan-100 bg-cyan-50 px-4 py-4 sm:px-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-black text-cyan-950">Kehadiran dari presensi mahasiswa</p>
                    <p class="mt-1 text-sm text-cyan-800">{{ data_get($attendance, 'present_days', 0) }} dari {{ data_get($attendance, 'expected_days', 0) }} hari terpenuhi · {{ number_format((float) data_get($attendance, 'percentage', 0), 1) }}%</p>
                </div>
                <span class="w-fit rounded-xl bg-white px-4 py-2 text-sm font-black text-cyan-800">Skor otomatis: {{ data_get($attendance, 'score', 1) }}/5</span>
            </div>
            <p class="mt-2 text-xs text-cyan-700">Skor ini membaca presensi berstatus disetujui. Selesaikan validasi presensi sebelum mengirim dan mengunci penilaian.</p>
        </div>
    @endif

    <div class="divide-y divide-slate-200">
        @foreach($sections as $section)
            <section class="px-4 py-5 sm:px-5">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-black text-slate-950">{{ $section['title'] }}</h3>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Bobot {{ $section['weight'] }}%</span>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach($section['criteria'] as $criterion)
                        @php
                            $savedValue = $criterion['automatic']
                                ? data_get($attendance, 'score', 1)
                                : ($isSubmittedForm ? old('criteria.'.$criterion['code'], $savedCriteria[$criterion['code']] ?? null) : ($savedCriteria[$criterion['code']] ?? null));
                        @endphp
                        <div class="py-4 first:pt-0 last:pb-0" data-criterion data-weight="{{ $criterion['weight'] }}">
                            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-bold text-slate-800">{{ $criterion['name'] }}</p>
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500">{{ $criterion['weight'] }}%</span>
                                        @if($criterion['automatic'])
                                            <span class="rounded-full bg-cyan-50 px-2 py-1 text-xs font-bold text-cyan-700">Otomatis</span>
                                        @endif
                                    </div>
                                </div>
                                @if($criterion['automatic'])
                                    <div class="grid h-11 w-14 place-items-center rounded-xl border-2 border-cyan-600 bg-cyan-50 text-sm font-black text-cyan-800" data-auto-score="{{ $savedValue }}">{{ $savedValue }}</div>
                                @else
                                    <div class="grid grid-cols-5 gap-2" role="radiogroup" aria-label="Skor {{ $criterion['name'] }}">
                                        @foreach($levelLabels as $value => $label)
                                            <label class="cursor-pointer">
                                                <input class="peer sr-only" type="radio" name="criteria[{{ $criterion['code'] }}]" value="{{ $value }}" @checked((string) $savedValue === (string) $value) @disabled($locked) @if(!$locked) required @endif>
                                                <span class="grid h-11 w-11 place-items-center rounded-xl border border-slate-200 bg-white text-sm font-black text-slate-600 transition peer-checked:border-cyan-700 peer-checked:bg-cyan-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-cyan-500">{{ $value }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-bold text-cyan-700">Lihat panduan skor</summary>
                                <div class="mt-2 grid gap-2 lg:grid-cols-5">
                                    @foreach($criterion['rubric'] as $value => $description)
                                        <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600"><span class="font-black text-slate-900">{{ $value }} · {{ $levelLabels[$value] }}</span><br>{{ $description }}</div>
                                    @endforeach
                                </div>
                            </details>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <section class="border-t border-slate-200 bg-slate-50 px-4 py-5 sm:px-5">
        <h3 class="text-base font-black text-slate-950">Catatan dan Umpan Balik</h3>
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Kelebihan Mahasiswa</span><textarea name="strengths" rows="4" class="rounded-xl border-slate-200 text-sm" @disabled($locked)>{{ $isSubmittedForm ? old('strengths', $feedback['strengths'] ?? '') : ($feedback['strengths'] ?? '') }}</textarea></label>
            <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Hal yang Perlu Ditingkatkan</span><textarea name="improvements" rows="4" class="rounded-xl border-slate-200 text-sm" @disabled($locked)>{{ $isSubmittedForm ? old('improvements', $feedback['improvements'] ?? '') : ($feedback['improvements'] ?? '') }}</textarea></label>
            <label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Saran Pengembangan</span><textarea name="development_suggestions" rows="4" class="rounded-xl border-slate-200 text-sm" @disabled($locked)>{{ $isSubmittedForm ? old('development_suggestions', $feedback['development_suggestions'] ?? '') : ($feedback['development_suggestions'] ?? '') }}</textarea></label>
        </div>
        <label class="mt-4 grid gap-2"><span class="text-sm font-bold text-slate-700">Komentar Tambahan</span><textarea name="overall_comments" rows="3" class="rounded-xl border-slate-200 text-sm" @disabled($locked)>{{ $isSubmittedForm ? old('overall_comments', $score->comments) : $score->comments }}</textarea></label>
    </section>

    <section class="border-t border-slate-200 px-4 py-5 sm:px-5">
        <h3 class="text-base font-black text-slate-950">Rekomendasi</h3>
        <p class="mt-1 text-sm text-slate-500">Wajib dilengkapi sebelum penilaian dikirim dan dikunci.</p>
        <div class="mt-4 grid gap-3 lg:grid-cols-3">
            @foreach([
                'competency_met' => 'Kompetensi PKPA Apotek terpenuhi',
                'portfolio_accepted' => 'Portofolio layak diterima',
                'final_exam_recommended' => 'Layak mengikuti ujian/seminar akhir',
            ] as $key => $label)
                <fieldset class="rounded-xl border border-slate-200 p-3">
                    <legend class="px-1 text-sm font-bold text-slate-700">{{ $label }}</legend>
                    <div class="mt-2 flex gap-2">
                        @foreach(['yes' => 'Ya', 'no' => 'Tidak'] as $value => $text)
                            @php $recommendationValue = $isSubmittedForm ? old('recommendations.'.$key, $recommendations[$key] ?? null) : ($recommendations[$key] ?? null); @endphp
                            <label class="flex-1 cursor-pointer"><input class="peer sr-only" type="radio" name="recommendations[{{ $key }}]" value="{{ $value }}" @checked($recommendationValue === $value) @disabled($locked) @if(!$locked) required @endif><span class="block rounded-lg border border-slate-200 px-3 py-2 text-center text-sm font-bold text-slate-600 peer-checked:border-cyan-700 peer-checked:bg-cyan-50 peer-checked:text-cyan-800">{{ $text }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
    </section>

    <div class="sticky bottom-0 flex flex-col gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <p class="text-xs text-slate-500">Draf masih dapat diperbaiki. Setelah dikirim, nilai terkunci.</p>
        <div class="flex flex-wrap gap-2">
            <button formnovalidate class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700" @disabled($locked)>Simpan Draf</button>
            <button formaction="{{ route($routePrefix.'.pkpa-assessments.scores.submit', $score) }}" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-black text-white" @disabled($locked)>Kirim & Kunci</button>
        </div>
    </div>
</form>
</details>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-assessment-form]').forEach((form) => {
                const calculate = () => {
                    let total = 0;
                    form.querySelectorAll('[data-criterion]').forEach((row) => {
                        const weight = Number(row.dataset.weight || 0);
                        const selected = row.querySelector('input[type="radio"]:checked');
                        const auto = row.querySelector('[data-auto-score]');
                        const value = Number(selected?.value || auto?.dataset.autoScore || 0);
                        total += weight * value / 5;
                    });
                    const output = form.querySelector('[data-assessment-total]');
                    if (output) output.textContent = total.toFixed(2);
                };
                form.addEventListener('change', calculate);
                calculate();
            });
        });
    </script>
@endonce
