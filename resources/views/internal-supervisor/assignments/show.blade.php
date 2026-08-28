@extends('layouts.app')
@section('title','Detail Mahasiswa Bimbingan - '.config('app.name'))
@section('page_title','Detail Mahasiswa Bimbingan')
@section('content')
<section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><span class="rounded-full {{ $assignment->statusBadgeClass() }} px-3 py-1 text-xs font-semibold">{{ $assignment->statusLabel() }}</span><h2 class="mt-4 text-2xl font-bold">{{ $assignment->student->user->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $assignment->student->nim ?: '-' }} · {{ $assignment->place->name }}</p><div class="mt-5 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">Gunakan menu Logbook Mahasiswa untuk memantau aktivitas dan catatan akademik mahasiswa bimbingan ini.</div></section>
@endsection
