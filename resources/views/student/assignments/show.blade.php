@extends('layouts.app')
@section('title','Penempatan PKPA - '.config('app.name'))
@section('page_title','Penempatan PKPA')
@section('content')
@if(! $selection && ! $assignment)
<section class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200"><h2 class="text-xl font-bold">Anda belum memiliki tempat PKPA</h2><p class="mt-2 text-sm text-slate-500">Silakan mengikuti pemilihan tempat terlebih dahulu.</p></section>
@elseif(! $assignment)
<section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><h2 class="text-xl font-bold">{{ $selection->place->name }}</h2><p class="mt-2 text-sm text-slate-500">Menunggu penetapan pembimbing oleh koordinator PKPA.</p></section>
@else
<section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><span class="rounded-full {{ $assignment->statusBadgeClass() }} px-3 py-1 text-xs font-semibold">{{ $assignment->statusLabel() }}</span><h2 class="mt-4 text-2xl font-bold">{{ $assignment->place->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $assignment->period->name }} · {{ $assignment->place->address ?: 'Alamat belum diisi' }}</p><div class="mt-6 grid gap-4 md:grid-cols-2"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Pembimbing Dalam</p><p class="mt-2 font-bold">{{ $assignment->internalSupervisor ? lecturer_display_name($assignment->internalSupervisor) : 'Belum ditentukan' }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Preseptor</p><p class="mt-2 font-bold">{{ $assignment->fieldSupervisor ? field_supervisor_display_name($assignment->fieldSupervisor) : 'Belum ditentukan' }}</p></div></div>@if(! $assignment->isCompleteSupervision())<div class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">Menunggu kelengkapan pembimbing.</div>@endif</section>
@endif
@endsection
