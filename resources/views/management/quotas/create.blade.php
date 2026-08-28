@extends('layouts.app')
@section('title', 'Tambah Kapasitas Tempat PKPA - '.config('app.name'))
@section('page_title', 'Tambah Kapasitas Tempat PKPA')
@section('content')<form method="POST" action="{{ route('management.kp-place-quotas.store') }}">@include('management.quotas._form')</form>@endsection
