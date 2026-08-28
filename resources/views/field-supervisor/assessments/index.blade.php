@extends('layouts.app')
@section('title','Penilaian Preseptor - '.config('app.name'))
@section('page_title','Penilaian Preseptor')
@section('content')
@include('shared.assessments.assignment-list', ['assignments' => $assignments, 'title' => 'Mahasiswa PKPA', 'routeName' => 'field-supervisor.assessments.show'])
@endsection
