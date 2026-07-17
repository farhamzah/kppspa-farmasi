<div class="space-y-6">
    <div class="rounded-3xl border border-sky-100 bg-white p-6 shadow-sm">
        <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Antrian Penilaian PKPA</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">{{ $title }}</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Isi draft nilai untuk komponen yang ditugaskan kepada Anda. Nilai yang sudah dikirim akan terkunci dan perubahan berikutnya harus melalui revisi.</p>
    </div>
    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
    @endif
    <div class="grid gap-4">
        @forelse ($assignments as $assignment)
            <article class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="font-black text-slate-950">{{ $assignment->assessment?->rotationRun?->student_core_user_id }}</p>
                        <p class="text-sm text-slate-500">{{ $assignment->assessment?->rotationRun?->practiceDomain?->name }} · {{ $assignment->assessment?->rotationRun?->practiceSite?->name }}</p>
                        <p class="mt-1 text-xs font-bold uppercase text-cyan-700">{{ $assignment->component?->name }} · {{ $assignment->status }}</p>
                    </div>
                </div>
                @foreach ($assignment->scores as $score)
                    <form method="POST" action="{{ route($routePrefix.'.pkpa-assessments.scores.save', $score) }}" class="mt-4 grid gap-3 md:grid-cols-[160px_1fr_auto_auto]">
                        @csrf
                        <input name="raw_score" type="number" step="0.0001" min="0" max="{{ $score->component?->maximum_raw_score }}" value="{{ $score->raw_score }}" class="rounded-2xl border-slate-200 text-sm" placeholder="Nilai">
                        <input name="comments" value="{{ $score->comments }}" class="rounded-2xl border-slate-200 text-sm" placeholder="Komentar ringkas">
                        <button class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-bold text-white" @disabled(in_array($score->status, ['submitted','approved','locked']))>Simpan</button>
                    </form>
                    <form method="POST" action="{{ route($routePrefix.'.pkpa-assessments.scores.submit', $score) }}" class="mt-2">
                        @csrf
                        <button class="rounded-2xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white" @disabled(in_array($score->status, ['submitted','approved','locked']))>Kirim & Kunci</button>
                    </form>
                @endforeach
            </article>
        @empty
            <div class="rounded-3xl border border-slate-100 bg-white p-6 text-sm text-slate-500">Belum ada penilaian yang ditugaskan.</div>
        @endforelse
    </div>
    {{ $assignments->links() }}
</div>
