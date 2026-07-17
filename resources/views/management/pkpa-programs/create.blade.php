@extends('layouts.app')
@section('title', 'Tambah Program PKPA - '.config('app.name'))
@section('page_title', 'Tambah Program PKPA')
@section('content')
<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <form method="POST" action="{{ route('management.pkpa-programs.store') }}">
        @include('management.pkpa-programs._form')
    </form>
</div>
@endsection
