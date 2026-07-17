@extends('layouts.app')

@section('title', 'Penilaian PKPA')

@section('content')
@include('shared.pkpa-assessment-queue', ['title' => 'Penilaian Pembimbing Dalam', 'assignments' => $assignments, 'routePrefix' => 'internal-supervisor'])
@endsection
