@extends('layouts.app')
@section('title','Penilaian Pembimbing Dalam - '.config('app.name'))
@section('page_title','Penilaian Pembimbing Dalam')
@section('content')
@include('shared.assessments.assignment-list', ['assignments' => $assignments, 'title' => 'Mahasiswa Bimbingan', 'routeName' => 'internal-supervisor.assessments.show'])
@endsection
