@extends('layouts.app')
@section('title', 'Edit Kelompok PKPA - '.config('app.name'))
@section('page_title', 'Edit Kelompok PKPA')
@section('content')
@if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><form method="POST" action="{{ route('management.pkpa-student-groups.update', $group) }}">@method('PUT')@include('management.pkpa-student-groups._form')</form></div>
@endsection
