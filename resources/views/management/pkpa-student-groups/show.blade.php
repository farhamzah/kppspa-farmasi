@extends('layouts.app')
@section('title', 'Detail Kelompok PKPA - '.config('app.name'))
@section('page_title', 'Detail Kelompok PKPA')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div><p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $group->program?->code }}</p><h2 class="mt-2 text-2xl font-black">{{ $group->code }} - {{ $group->name }}</h2><p class="mt-1 text-sm text-slate-600">{{ $group->description ?: 'Belum ada deskripsi.' }}</p></div>
            <a href="{{ route('management.pkpa-student-groups.edit', $group) }}" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Edit Kelompok</a>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Status</p><p class="mt-1 font-black">{{ str($group->status)->headline() }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Aktif</p><p class="mt-1 font-black">{{ $group->is_active ? 'Ya' : 'Tidak' }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Anggota</p><p class="mt-1 font-black">{{ $group->activeMembers->count() }}</p></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Sisa Kapasitas</p><p class="mt-1 font-black">{{ is_null($group->remainingCapacity()) ? 'Tidak dibatasi' : $group->remainingCapacity() }}</p></div>
        </div>
    </div>
    <div class="grid gap-5 xl:grid-cols-[1fr_380px]">
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-black">Anggota aktif</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Mahasiswa</th><th class="px-4 py-3">Masuk</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($group->activeMembers as $member)
                        <tr><td class="px-4 py-4"><div class="font-bold">{{ $member->enrollment?->student_name_snapshot }}</div><div class="text-xs text-slate-500">{{ $member->enrollment?->student_number }} / {{ $member->enrollment?->core_user_id }}</div></td><td class="px-4 py-4">{{ $member->joined_at?->format('d M Y') ?: '-' }}</td><td class="px-4 py-4 text-right"><form method="POST" action="{{ route('management.pkpa-student-groups.members.destroy', [$group, $member]) }}">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-700">Keluarkan</button></form></td></tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-slate-500">Belum ada anggota aktif.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <aside class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-black">Tambah anggota</h3>
            <form method="POST" action="{{ route('management.pkpa-student-groups.members.store', $group) }}" class="mt-4 space-y-3">@csrf<select name="pkpa_enrollment_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required><option value="">Pilih peserta belum berkelompok</option>@foreach($availableEnrollments as $enrollment)<option value="{{ $enrollment->id }}">{{ $enrollment->student_number }} - {{ $enrollment->student_name_snapshot }}</option>@endforeach</select><textarea name="notes" rows="2" placeholder="Catatan opsional" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea><button class="w-full rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambah</button></form>
            <form method="POST" action="{{ route('management.pkpa-student-groups.members.bulk', $group) }}" class="mt-6 space-y-3">@csrf<p class="text-sm font-bold text-slate-700">Tambah massal</p><div class="max-h-64 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3">@forelse($availableEnrollments as $enrollment)<label class="flex gap-2 text-sm"><input type="checkbox" name="enrollment_ids[]" value="{{ $enrollment->id }}"><span>{{ $enrollment->student_number }} - {{ $enrollment->student_name_snapshot }}</span></label>@empty<p class="text-sm text-slate-500">Tidak ada peserta tersedia.</p>@endforelse</div><button class="w-full rounded-xl border border-cyan-200 px-4 py-2 text-sm font-black text-cyan-700">Terapkan Massal</button></form>
        </aside>
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-black">Histori anggota</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Mahasiswa</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Masuk</th><th class="px-4 py-3">Keluar</th></tr></thead>
                <tbody class="divide-y divide-slate-100">@foreach($group->members as $member)<tr><td class="px-4 py-4">{{ $member->enrollment?->student_name_snapshot }}</td><td class="px-4 py-4">{{ str($member->status)->headline() }}</td><td class="px-4 py-4">{{ $member->joined_at?->format('d M Y H:i') ?: '-' }}</td><td class="px-4 py-4">{{ $member->left_at?->format('d M Y H:i') ?: '-' }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    </div>
</div>
@endsection
