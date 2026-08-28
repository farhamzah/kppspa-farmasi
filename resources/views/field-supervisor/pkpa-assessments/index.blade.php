@extends('layouts.app')

@section('title', 'Penilaian PKPA')

@section('content')
@include('shared.pkpa-assessment-queue', ['title' => 'Penilaian Preseptor', 'assignments' => $assignments, 'routePrefix' => 'field-supervisor'])
@endsection
