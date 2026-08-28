@extends('layouts.app')
@section('title', 'Edit Tempat PKPA - '.config('app.name'))
@section('page_title', 'Edit Tempat PKPA')
@section('content')<form method="POST" action="{{ route('management.kp-places.update', $place) }}">@include('management.places._form')</form>@endsection
