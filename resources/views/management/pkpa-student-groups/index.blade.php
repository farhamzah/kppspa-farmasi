@extends('layouts.app')
@section('title', 'Kelompok Opsional - '.config('app.name'))
@section('page_title', 'Kelompok Opsional')
@section('content')
<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        Fitur ini opsional. Gunakan hanya bila perlu mengelompokkan mahasiswa untuk pembekalan, koordinasi internal, atau administrasi lain di luar penempatan inti PKPA.
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" class="grid flex-1 gap-3 md:grid-cols-[1fr_180px_150px_auto]">
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Cari</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kode atau nama" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Program</label><select name="program_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->code }}</option>@endforeach</select></div>
                <div><label class="text-xs font-black uppercase tracking-widest text-slate-500">Status</label><select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach(\App\Models\PkpaStudentGroup::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></div>
                <button class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Filter</button>
            </form>
            <a href="{{ route('management.pkpa-student-groups.create') }}" class="rounded-xl bg-cyan-700 px-4 py-2 text-center text-sm font-black text-white">Tambah Kelompok Opsional</a>
        </div>
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($groups as $group)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $group->program?->code }}</p><h2 class="mt-1 text-xl font-black text-slate-950">{{ $group->code }}</h2><p class="text-sm font-bold text-slate-600">{{ $group->name }}</p></div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-black">{{ str($group->status)->headline() }}</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-black uppercase text-slate-500">Anggota</p><p class="mt-1 text-xl font-black">{{ $group->active_members_count }}</p></div>
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-black uppercase text-slate-500">Kapasitas</p><p class="mt-1 text-xl font-black">{{ $group->maximum_members ?: 'Bebas' }}</p></div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('management.pkpa-student-groups.show', $group) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold">Detail</a><a href="{{ route('management.pkpa-student-groups.edit', $group) }}" class="rounded-lg border border-cyan-200 px-3 py-1.5 text-xs font-bold text-cyan-700">Edit</a></div>
            </div>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center text-slate-500 shadow-sm ring-1 ring-slate-200 md:col-span-2 xl:col-span-3">Belum ada kelompok opsional. Ini tidak menghambat penempatan PKPA.</div>
        @endforelse
    </div>
    {{ $groups->links() }}
</div>
@endsection
