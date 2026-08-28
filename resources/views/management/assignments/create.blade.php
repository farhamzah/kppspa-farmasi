@extends('layouts.app')
@section('title','Buat Penempatan PKPA - '.config('app.name'))
@section('page_title','Buat Penempatan PKPA')
@section('content')
<div class="space-y-4">
<a href="{{ route('management.pkpa-assignments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300 hover:text-cyan-700">
    Kembali ke Penempatan PKPA
</a>
<section class="max-w-3xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('management.kp-assignments.store') }}" class="space-y-4">
        @csrf

        @include('management.assignments.partials.form-fields', ['assignment' => null])

        <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Simpan Penempatan</button>
    </form>
</section>
</div>
@endsection
