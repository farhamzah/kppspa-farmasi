@extends('layouts.app')
@section('title','Mahasiswa Bimbingan - '.config('app.name'))
@section('page_title','Mahasiswa Bimbingan')
@section('content')
@include('shared.assignments.grouped-student-list', [
    'counterpartLabel' => 'Pembimbing Dalam',
    'counterpartType' => 'internal',
    'detailRoute' => 'field-supervisor.pkpa-students.show',
    'emptyMessage' => 'Belum ada mahasiswa bimbingan.',
])
@endsection
