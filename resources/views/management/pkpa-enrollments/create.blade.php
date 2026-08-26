@extends('layouts.app')
@section('title', 'Tambah Peserta PKPA - '.config('app.name'))
@section('page_title', 'Tambah Peserta PKPA')
@section('content')
@php
    $oldSelectedStudents = collect(old('selected_students', []))
        ->filter(fn ($student) => filled($student['core_user_id'] ?? null))
        ->map(fn ($student) => [
            'core_user_id' => (string) ($student['core_user_id'] ?? ''),
            'name' => (string) ($student['name'] ?? ''),
            'email' => (string) ($student['email'] ?? ''),
            'student_number' => (string) ($student['student_number'] ?? ''),
        ])
        ->values();
@endphp
<div class="max-w-5xl space-y-5">
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    @if(session('warning'))<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">{{ session('warning') }}</div>@endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('management.pkpa-enrollments.store') }}" class="space-y-5" data-pkpa-enrollment-form>
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program PKPA</label><select name="pkpa_program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required><option value="">Pilih program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(old('pkpa_program_id') == $program->id)>{{ $program->code }} - {{ $program->name }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Kelompok Opsional</label><select name="pkpa_student_group_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Belum dikelompokkan</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('pkpa_student_group_id') == $group->id)>{{ $group->program?->code }} / {{ $group->code }} - {{ $group->name }}</option>@endforeach</select></div>
                <div class="md:col-span-2">
                    <div
                        class="space-y-3"
                        data-student-multi-picker
                        data-search-url="{{ route('management.core-directory.students') }}"
                        data-old-selected='@json($oldSelectedStudents, JSON_THROW_ON_ERROR)'
                    >
                        <div>
                            <label class="text-xs font-black uppercase tracking-widest text-slate-500">Mahasiswa Dari Core</label>
                            <input
                                type="text"
                                placeholder="Ketik nama mahasiswa, email, NPM, atau Core ID"
                                autocomplete="off"
                                class="mt-1 h-14 w-full rounded-2xl border border-slate-300 px-4 text-base shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20"
                                data-student-search
                            >
                            <p class="mt-2 text-xs text-slate-500">Mahasiswa yang sudah terdaftar di program ini otomatis tidak ditampilkan lagi. Gunakan centang untuk memilih beberapa peserta sekaligus.</p>
                        </div>

                        <div class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-3" data-bulk-actions>
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" class="rounded-xl bg-cyan-700 px-4 py-2.5 text-sm font-black text-white" data-select-all-visible>Pilih semua hasil</button>
                                <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700" data-clear-selected>Bersihkan pilihan</button>
                                <span class="text-sm font-semibold text-slate-500" data-selected-summary>Belum ada peserta dipilih.</span>
                            </div>
                        </div>

                        <div class="hidden max-h-[28rem] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" data-search-results></div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-900">Peserta yang akan ditambahkan</p>
                                    <p class="text-xs text-slate-500">Semua peserta terpilih akan dibuat sekaligus saat Anda menekan tombol simpan.</p>
                                </div>
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-cyan-700 ring-1 ring-slate-200" data-selected-count>0 peserta</span>
                            </div>
                            <div class="mt-4 space-y-2" data-selected-list>
                                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-500" data-empty-selected>Belum ada mahasiswa dipilih.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Catatan</label><textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea></div>
            <div class="rounded-xl border border-cyan-100 bg-cyan-50 px-4 py-3 text-sm text-cyan-800">Sistem akan memvalidasi mahasiswa ke Core, menolak akun nonaktif atau role yang tidak sesuai, lalu membuat kewajiban wahana otomatis.</div>
            <div class="flex flex-wrap gap-2"><button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah Peserta</button><a href="{{ route('management.pkpa-enrollments.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Batal</a></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-student-multi-picker]');
    if (!root) {
        return;
    }

    const form = root.closest('form');
    const programSelect = form?.querySelector('[name="pkpa_program_id"]');
    const searchInput = root.querySelector('[data-student-search]');
    const resultsBox = root.querySelector('[data-search-results]');
    const selectedList = root.querySelector('[data-selected-list]');
    const selectedCount = root.querySelector('[data-selected-count]');
    const selectedSummary = root.querySelector('[data-selected-summary]');
    const emptySelected = root.querySelector('[data-empty-selected]');
    const bulkActions = root.querySelector('[data-bulk-actions]');
    const selectAllButton = root.querySelector('[data-select-all-visible]');
    const clearSelectedButton = root.querySelector('[data-clear-selected]');
    const searchUrl = root.dataset.searchUrl;
    const oldSelected = JSON.parse(root.dataset.oldSelected || '[]');
    const selectedStudents = new Map();
    let requestCounter = 0;
    let currentResults = [];

    const debounce = (callback, wait = 250) => {
        let timeoutId = null;

        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = window.setTimeout(() => callback(...args), wait);
        };
    };

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    };

    const syncHiddenInputs = () => {
        form.querySelectorAll('input[data-selected-student-hidden="true"]').forEach((input) => input.remove());

        Array.from(selectedStudents.values()).forEach((student, index) => {
            [
                ['core_user_id', student.core_user_id],
                ['student_number', student.student_number],
                ['name', student.name],
                ['email', student.email],
            ].forEach(([field, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `selected_students[${index}][${field}]`;
                input.value = value || '';
                input.dataset.selectedStudentHidden = 'true';
                form.appendChild(input);
            });
        });
    };

    const renderSelected = () => {
        const students = Array.from(selectedStudents.values());
        selectedList.querySelectorAll('[data-selected-item]').forEach((node) => node.remove());

        if (!students.length) {
            emptySelected.classList.remove('hidden');
        } else {
            emptySelected.classList.add('hidden');
        }

        students.forEach((student) => {
            const item = document.createElement('div');
            item.dataset.selectedItem = student.core_user_id;
            item.className = 'flex items-start justify-between gap-3 rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200';
            item.innerHTML = `
                <div class="min-w-0">
                    <p class="text-sm font-black text-slate-900">${escapeHtml(student.name || student.core_user_id)}</p>
                    <p class="mt-1 text-xs text-slate-500">${escapeHtml([student.email, student.student_number].filter(Boolean).join(' • '))}</p>
                </div>
                <button type="button" class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50" data-remove-selected="${escapeHtml(student.core_user_id)}">Hapus</button>
            `;
            selectedList.appendChild(item);
        });

        selectedCount.textContent = `${students.length} peserta`;
        selectedSummary.textContent = students.length
            ? `${students.length} peserta siap ditambahkan ke program ini.`
            : 'Belum ada peserta dipilih.';
        bulkActions.classList.toggle('hidden', !programSelect?.value);

        selectedList.querySelectorAll('[data-remove-selected]').forEach((button) => {
            button.addEventListener('click', () => {
                selectedStudents.delete(button.dataset.removeSelected);
                renderSelected();
                renderResults(currentResults);
            });
        });

        syncHiddenInputs();
    };

    const normalizeStudent = (student) => ({
        core_user_id: String(student.core_user_id || ''),
        name: student.name || student.label || '',
        email: student.email || '',
        student_number: student.student_number || '',
    });

    const addStudent = (student) => {
        const normalized = normalizeStudent(student);
        if (!normalized.core_user_id) {
            return;
        }

        selectedStudents.set(normalized.core_user_id, normalized);
        renderSelected();
        renderResults(currentResults);
    };

    const renderResults = (items) => {
        currentResults = items || [];

        if (!programSelect?.value) {
            resultsBox.innerHTML = '<div class="rounded-xl px-3 py-3 text-sm text-slate-500">Pilih program PKPA terlebih dahulu.</div>';
            resultsBox.classList.remove('hidden');
            return;
        }

        if (!currentResults.length) {
            resultsBox.innerHTML = '<div class="rounded-xl px-3 py-3 text-sm text-slate-500">Data tidak ditemukan di Core.</div>';
            resultsBox.classList.remove('hidden');
            return;
        }

        resultsBox.innerHTML = currentResults.map((student) => {
            const selected = selectedStudents.has(String(student.core_user_id));
            const secondary = [student.email, student.student_number].filter(Boolean).join(' • ');

            return `
                <label class="flex cursor-pointer items-start gap-3 rounded-xl px-3 py-3 transition ${selected ? 'bg-slate-100 opacity-60' : 'hover:bg-sky-50'}">
                    <input type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" data-result-checkbox="${escapeHtml(String(student.core_user_id))}" ${selected ? 'checked disabled' : ''}>
                    <span class="min-w-0">
                        <span class="block text-sm font-black text-slate-900">${escapeHtml(student.name || student.label || student.core_user_id)}</span>
                        <span class="mt-1 block text-xs text-slate-500">${escapeHtml(secondary)}</span>
                    </span>
                </label>
            `;
        }).join('');

        resultsBox.classList.remove('hidden');

        resultsBox.querySelectorAll('[data-result-checkbox]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (!checkbox.checked) {
                    return;
                }

                const student = currentResults.find((item) => String(item.core_user_id) === checkbox.dataset.resultCheckbox);
                if (student) {
                    addStudent(student);
                }
            });
        });
    };

    const fetchResults = debounce(async (query) => {
        if (!programSelect?.value) {
            renderResults([]);
            return;
        }

        requestCounter += 1;
        const currentRequest = requestCounter;
        const params = new URLSearchParams({
            limit: '20',
            program_id: programSelect.value,
        });

        if ((query || '').trim() !== '') {
            params.set('q', query.trim());
        }

        resultsBox.innerHTML = '<div class="rounded-xl px-3 py-3 text-sm text-slate-500">Memuat data Core...</div>';
        resultsBox.classList.remove('hidden');

        try {
            const response = await fetch(`${searchUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            if (currentRequest !== requestCounter) {
                return;
            }

            renderResults(Array.isArray(payload.data) ? payload.data : []);
        } catch (error) {
            if (currentRequest !== requestCounter) {
                return;
            }

            resultsBox.innerHTML = '<div class="rounded-xl px-3 py-3 text-sm text-rose-600">Pencarian Core belum tersedia. Coba lagi sebentar.</div>';
            resultsBox.classList.remove('hidden');
        }
    }, 250);

    searchInput.addEventListener('focus', () => fetchResults(searchInput.value));
    searchInput.addEventListener('input', () => fetchResults(searchInput.value));
    programSelect?.addEventListener('change', () => {
        selectedStudents.clear();
        renderSelected();
        searchInput.value = '';
        fetchResults('');
    });

    selectAllButton?.addEventListener('click', () => {
        currentResults.forEach((student) => {
            addStudent(student);
        });
    });

    clearSelectedButton?.addEventListener('click', () => {
        selectedStudents.clear();
        renderSelected();
        renderResults(currentResults);
    });

    form?.addEventListener('submit', (event) => {
        if (!selectedStudents.size) {
            event.preventDefault();
            searchInput.focus();
            resultsBox.innerHTML = '<div class="rounded-xl px-3 py-3 text-sm text-rose-600">Pilih minimal satu mahasiswa dari daftar Core terlebih dahulu.</div>';
            resultsBox.classList.remove('hidden');
        }
    });

    oldSelected.forEach((student) => {
        addStudent(student);
    });

    renderSelected();
})();
</script>
@endpush
