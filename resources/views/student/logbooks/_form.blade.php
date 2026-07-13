@csrf
<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="text-sm font-semibold text-slate-700">Tanggal Kegiatan</label>
        <input type="date" name="activity_date" value="{{ old('activity_date', optional($logbook?->activity_date ?? null)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('activity_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-sm font-semibold text-slate-700">Jam Mulai</label>
            <input type="time" name="start_time" value="{{ old('start_time', $logbook?->start_time ? substr($logbook->start_time, 0, 5) : '') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-sm font-semibold text-slate-700">Jam Selesai</label>
            <input type="time" name="end_time" value="{{ old('end_time', $logbook?->end_time ? substr($logbook->end_time, 0, 5) : '') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @error('end_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="md:col-span-2">
        <label class="text-sm font-semibold text-slate-700">Judul Kegiatan</label>
        <input name="activity_title" value="{{ old('activity_title', $logbook?->activity_title) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: Pelayanan resep rawat jalan">
        @error('activity_title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label class="text-sm font-semibold text-slate-700">Uraian Kegiatan</label>
        <textarea name="activity_description" rows="5" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('activity_description', $logbook?->activity_description) }}</textarea>
        @error('activity_description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-700">Hasil Pembelajaran</label>
        <textarea name="learning_outcome" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('learning_outcome', $logbook?->learning_outcome) }}</textarea>
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-700">Kendala</label>
        <textarea name="obstacle" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('obstacle', $logbook?->obstacle) }}</textarea>
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-700">Solusi</label>
        <textarea name="solution" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('solution', $logbook?->solution) }}</textarea>
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-700">Bukti Kegiatan Opsional</label>
        <input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,application/pdf,image/jpeg,image/png,image/webp,image/heic,image/heif" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <p class="mt-1 text-xs text-slate-500">Format: PDF, JPG, JPEG, PNG, WebP, HEIC, HEIF. Maksimal 5MB.</p>
        @if($logbook?->hasEvidenceFile())
            <p class="mt-1 text-xs text-teal-700">Bukti saat ini: {{ $logbook->evidence_original_filename }}</p>
        @endif
        @error('evidence')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-700">Link Bukti Kegiatan Opsional</label>
        <input type="text" inputmode="url" name="evidence_url" value="{{ old('evidence_url', $logbook?->evidence_url) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="https://drive.google.com/... atau drive.google.com/...">
        <input name="evidence_url_label" value="{{ old('evidence_url_label', $logbook?->evidence_url_label) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Label link, contoh: Foto kegiatan hari 1">
        <p class="mt-1 text-xs text-slate-500">Jika file dari HP sulit diunggah, unggah ke Google Drive lalu tempel link berbagi di sini. Link Drive tanpa https:// akan dirapikan otomatis.</p>
        @if($logbook?->hasEvidenceLink())
            <p class="mt-1 text-xs text-sky-700">Link saat ini: {{ $logbook->evidenceLabel() }}</p>
        @endif
        @error('evidence_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('evidence_url_label')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
<div class="mt-6 flex flex-wrap gap-3">
    <button name="action" value="draft" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Simpan Draft</button>
    <button name="action" value="submit" onclick="return confirm('Submit logbook untuk validasi pembimbing lapangan?')" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Submit untuk Validasi</button>
    <a href="{{ route('student.logbooks.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Batal</a>
</div>
