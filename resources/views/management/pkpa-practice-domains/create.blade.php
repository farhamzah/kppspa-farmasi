@extends('layouts.app')
@section('title', 'Tambah Wahana PKPA - '.config('app.name'))
@section('page_title', 'Tambah Wahana PKPA')
@section('content')
<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><form method="POST" action="{{ route('management.pkpa-practice-domains.store') }}">@include('management.pkpa-practice-domains._form')</form></div>
@endsection
