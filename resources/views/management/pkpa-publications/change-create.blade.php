@extends('layouts.app')
@section('title', 'Permintaan Perubahan PKPA - '.config('app.name'))
@section('page_title', 'Permintaan Perubahan PKPA')

@section('content')
<div class="space-y-5">
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Revisi publikasi</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $publication->code }}</h2>
        <p class="mt-1 text-sm text-slate-500">Perubahan jadwal resmi tidak mengubah salinan lama. Setelah disetujui dan diterapkan, sistem membuat revisi publikasi baru.</p>
        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
            <p class="font-black text-slate-950">Kapan form ini dipakai?</p>
            <p class="mt-1">Gunakan halaman ini jika jadwal resmi sudah terbit lalu perlu koreksi tanggal, tempat, atau pembimbing. Jika masih tahap draft, perbaikannya dilakukan di planner penempatan, bukan di sini.</p>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('management.pkpa-change-requests.store', $publication) }}" class="grid gap-4 lg:grid-cols-2">
            @csrf
            <div class="lg:col-span-2">
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Jadwal Yang Direvisi</label>
                <select name="assignment_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                    @foreach($publication->assignments as $assignment)
                        <option value="{{ $assignment->id }}">{{ $assignment->student_name_snapshot }} - {{ $assignment->practice_domain_name_snapshot }} - {{ $assignment->practice_site_name_snapshot }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Jenis perubahan</label>
                <select name="request_type" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                    <option value="date_change">Ubah tanggal</option>
                    <option value="site_change">Ubah tempat</option>
                    <option value="supervisor_change">Ubah pembimbing</option>
                    <option value="administrative_correction">Koreksi administrasi</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Alasan</label>
                <input name="reason" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Tanggal mulai baru</label>
                <input name="start_date" type="date" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Tanggal selesai baru</label>
                <input name="end_date" type="date" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="lg:col-span-2">
                <label class="text-xs font-black uppercase tracking-widest text-slate-500">Catatan perubahan</label>
                <textarea name="notes" rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
            </div>
            <div class="lg:col-span-2 flex flex-wrap gap-2">
                <a href="{{ route('management.pkpa-publications.show', $publication) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">Batal</a>
                <button class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-black text-white">Simpan Permintaan Perubahan</button>
            </div>
        </form>
    </section>
</div>
@endsection
