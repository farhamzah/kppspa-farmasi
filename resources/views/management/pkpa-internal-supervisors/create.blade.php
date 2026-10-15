@extends('layouts.app')
@section('title', 'Tambah Pembimbing Dalam - '.config('app.name'))
@section('page_title', 'Tambah Pembimbing Dalam')
@section('content')
<div class="max-w-3xl space-y-5">
    <a href="{{ route('management.pkpa-internal-supervisors.index', ['program_id' => $selectedProgramId]) }}" class="inline-flex min-h-11 items-center text-sm font-bold text-cyan-700">Kembali ke Pembimbing Dalam</a>
    @if($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    <h2 class="text-xl font-black text-slate-950">Tambah dosen dari Core</h2>
    <form method="POST" action="{{ route('management.pkpa-internal-supervisors.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="mode" value="single">
        <input type="hidden" name="status" value="active">
        <label class="block text-sm font-bold text-slate-700">Program PKPA<select name="pkpa_program_id" required class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"><option value="">Pilih program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(old('pkpa_program_id', $selectedProgramId) == $program->id)>{{ $program->code }} - {{ $program->name }}</option>@endforeach</select></label>
        <x-management.core-directory-picker field-name="core_user_id" field-label="Dosen dari Core" :search-url="route('management.core-directory.lecturer-candidates')" :min-search-length="2" placeholder="Ketik minimal 2 huruf nama, email, atau NIDN" :required="true" :value="old('core_user_id')" helper="Akses KP/TA berbeda dari MY PKPA. Dosen yang belum memenuhi akses ditampilkan dengan alasan dan belum dapat dipilih." />
        <p class="border-l-4 border-cyan-400 bg-cyan-50 p-4 text-sm text-cyan-950">Dosen pilihan akan diaktifkan untuk seluruh wahana aktif program. Penambahan ini tidak mengubah penugasan mahasiswa atau status dosen lain.</p>
        <p class="text-sm text-slate-600">Jika dosen tidak ditemukan, periksa data dosen, status akun, dan akses MY PKPA di Core Farmasi. Tidak perlu membuat akun duplikat.</p>
        <div class="flex flex-wrap gap-3"><button class="min-h-11 rounded-lg bg-cyan-700 px-4 py-2 text-sm font-black text-white">Tambahkan ke Semua Wahana</button><a href="{{ route('management.pkpa-internal-supervisors.index', ['program_id' => $selectedProgramId]) }}" class="inline-flex min-h-11 items-center px-3 text-sm font-bold text-slate-600">Batal</a></div>
    </form>
</div>
@endsection
