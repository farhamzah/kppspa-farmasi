@extends('layouts.app')
@section('title', 'Detail Peserta PKPA - '.config('app.name'))
@section('page_title', 'Detail Peserta PKPA')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div><p class="text-xs font-black uppercase tracking-widest text-cyan-700">Identitas Core</p><h2 class="mt-2 text-2xl font-black text-slate-950">{{ $enrollment->student_name_snapshot ?: '-' }}</h2><p class="mt-1 text-sm text-slate-500">{{ $enrollment->student_number ?: '-' }} / {{ $enrollment->core_user_id }}</p></div>
                <form method="POST" action="{{ route('management.pkpa-enrollments.sync', $enrollment) }}">@csrf<button class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Sinkronkan Core</button></form>
            </div>
            <dl class="mt-6 grid gap-3 md:grid-cols-2">
                @foreach(['Email' => $enrollment->student_email_snapshot, 'Program studi' => $enrollment->study_program_snapshot, 'Angkatan' => $enrollment->cohort_snapshot, 'Status akademik' => $enrollment->academic_status_snapshot, 'Status akun Core' => $enrollment->core_account_status_snapshot, 'Last sync' => $enrollment->last_core_synced_at?->format('d M Y H:i') ?: '-'] as $label => $value)
                    <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs font-black uppercase tracking-widest text-slate-500">{{ $label }}</dt><dd class="mt-1 font-bold text-slate-900">{{ $value ?: '-' }}</dd></div>
                @endforeach
            </dl>
        </div>
        <aside class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Kepesertaan</p>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><span class="text-slate-500">Program</span><span class="text-right font-bold">{{ $enrollment->program?->code }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Status</span><span class="font-bold">{{ $enrollment->statusLabel() }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Kelompok</span><span class="font-bold">{{ $enrollment->activeGroupMembership?->group?->code ?: 'Belum' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Terdaftar</span><span class="font-bold">{{ $enrollment->enrolled_at?->format('d M Y') ?: '-' }}</span></div>
            </div>
            <form method="POST" action="{{ route('management.pkpa-enrollments.status', $enrollment) }}" class="mt-5 flex gap-2">@csrf<select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">@foreach(['active' => 'Aktif', 'on_hold' => 'Ditahan', 'archived' => 'Arsip'] as $value => $label)<option value="{{ $value }}" @selected($enrollment->status === $value)>{{ $label }}</option>@endforeach</select><button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Ubah</button></form>
            <form method="POST" action="{{ route('management.pkpa-enrollments.cancel', $enrollment) }}" class="mt-4 space-y-2">@csrf<textarea name="cancellation_reason" rows="2" placeholder="Alasan pembatalan" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea><button class="w-full rounded-xl border border-rose-200 px-4 py-2 text-sm font-black text-rose-700">Batalkan Kepesertaan</button></form>
        </aside>
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-black text-slate-950">Enam Kewajiban Wahana</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Wahana</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Pilihan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Kemajuan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach($enrollment->requirements as $requirement)
                    <tr>
                        <td class="px-4 py-4 font-bold">{{ $requirement->practiceDomain?->name }}</td>
                        <td class="px-4 py-4">{{ $requirement->modeLabel() }}</td>
                        <td class="px-4 py-4">{{ $requirement->selectedOption?->name ?? ($requirement->selection_mode === 'choose_one' ? 'Belum ditentukan' : '-') }}</td>
                        <td class="px-4 py-4">{{ str($requirement->status)->replace('_', ' ')->headline() }}</td>
                        <td class="px-4 py-4">{{ $requirement->completion_percentage }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
