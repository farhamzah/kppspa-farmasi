@extends('layouts.app')
@section('title','Tambah Logbook PKPA - '.config('app.name'))
@section('page_title','Tambah Logbook PKPA')
@section('content')
<section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <p class="mb-5 text-sm text-slate-500">Penempatan: <span class="font-semibold text-slate-900">{{ $assignment->place->name }}</span></p>
    <form method="POST" action="{{ route('student.pkpa-journals.store') }}" enctype="multipart/form-data">
        @include('student.logbooks._form', ['logbook' => null])
    </form>
</section>
@endsection
