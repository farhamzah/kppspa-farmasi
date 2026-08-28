@extends('layouts.app')
@section('title', 'Tambah Tempat PKPA - '.config('app.name'))
@section('page_title', 'Tambah Tempat PKPA')
@section('content')<form method="POST" action="{{ route('management.kp-places.store') }}">@include('management.places._form')</form>@endsection
