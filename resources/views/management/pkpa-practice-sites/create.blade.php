@extends('layouts.app')
@section('title', 'Tambah Tempat Praktik - '.config('app.name'))
@section('page_title', 'Tambah Tempat Praktik')
@section('content')
<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><form method="POST" action="{{ route('management.pkpa-practice-sites.store') }}">@include('management.pkpa-practice-sites._form')</form></div>
@endsection
