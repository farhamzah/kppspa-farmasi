@extends('layouts.app')
@section('title', 'Edit Kapasitas Tempat PKPA - '.config('app.name'))
@section('page_title', 'Edit Kapasitas Tempat PKPA')
@section('content')<form method="POST" action="{{ route('management.kp-place-quotas.update', $quota) }}">@include('management.quotas._form')</form>@endsection
