@extends('layouts.app')
@section('title', 'Tambah Periode PKPA - '.config('app.name'))
@section('page_title', 'Tambah Periode PKPA')
@section('content')
<form method="POST" action="{{ route('management.kp-periods.store') }}">@include('management.periods._form')</form>
@endsection
